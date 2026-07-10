<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\DTO\OrderBook;
use App\Domain\Exchange\DTO\PlaceOrderData;
use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketEvaluationService
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradingSettingsService $settings,
        private readonly OrderBookPricingService $pricing,
        private readonly ClientOrderIdFactory $clientIds,
        private readonly TradeRecorder $tradeRecorder,
        private readonly MarketBudgetService $marketBudgets,
    ) {}

    public function evaluate(Market $market): ?TradingOrder
    {
        if (! $market->is_active || ! $this->settings->marketEvaluationEnabled()) {
            return null;
        }

        $longOrder = $market->long_enabled ? $this->evaluateDirection($market, Deal::DIRECTION_LONG) : null;
        $shortOrder = $market->short_enabled ? $this->evaluateDirection($market, Deal::DIRECTION_SHORT) : null;

        return $longOrder ?? $shortOrder;
    }

    protected function evaluateDirection(Market $market, string $direction): ?TradingOrder
    {
        return Cache::lock("trading:market:{$market->id}:{$direction}", (int) config('trading.lock_ttl'))->block(5, function () use ($market, $direction): ?TradingOrder {
            $entrySide = $direction === Deal::DIRECTION_SHORT ? 'sell' : 'buy';

            if ($market->orders()->active()->where('side', $entrySide)->whereHas(
                'deal',
                fn ($deal) => $deal->where('direction', $direction),
            )->exists()) {
                return null;
            }

            if ($this->marketBudgets->availableForEntry($market, $direction) <= 0) {
                return null;
            }

            $mode = $this->settings->mode();
            $client = $this->exchanges->client($market->exchange, $mode);
            $fair = $client->getFairPrice($market->symbol);
            $book = $client->getOrderBook($market->symbol);
            $depthUsd = $this->settings->decimal('depth_usd');
            $usdtTmnPrice = $market->quote_asset === 'TMN'
                ? $this->pricing->averagePriceOfDepth(
                    $client->getOrderBook('USDTTMN'),
                    $direction === Deal::DIRECTION_SHORT ? 'asks' : 'bids',
                    $depthUsd,
                    'USDT',
                    depthInBaseAsset: true,
                )
                : null;

            $bookSide = $direction === Deal::DIRECTION_SHORT ? 'asks' : 'bids';
            $averageWallex = $this->pricing->averagePriceOfDepth($book, $bookSide, $depthUsd, $market->quote_asset, $usdtTmnPrice);
            $diff = $this->pricing->percentDifference($averageWallex, $fair->price);

            if ((float) $diff < (float) $this->settings->decimal('entry_threshold_percent')) {
                return null;
            }

            [$orderPrice, $amount] = $this->resolveEntryPriceAndAmount($market, $book, $direction, $client);

            if ($amount <= 0) {
                return null;
            }

            $orderSum = $orderPrice * $amount;
            if ($orderSum < $this->settings->entryMinOrderSum($market->quote_asset)) {
                return null;
            }

            $clientId = $this->clientIds->make($market, $entrySide);
            $placed = $client->placeOrder(new PlaceOrderData(
                symbol: $market->symbol,
                side: $entrySide,
                type: 'limit',
                price: number_format($orderPrice, $market->tick_size, '.', ''),
                amount: number_format($amount, $market->step_size, '.', ''),
                clientId: $clientId,
                mode: $mode,
            ));

            return $this->openDeal($market, $direction, $mode, $placed, $orderPrice, $amount, $clientId, $entrySide);
        });
    }

    /**
     * @return array{0: float, 1: float}
     */
    protected function resolveEntryPriceAndAmount(Market $market, OrderBook $book, string $direction, mixed $client): array
    {
        if ($direction === Deal::DIRECTION_SHORT) {
            $topAsk = $book->firstAskWithMinNotional((float) $this->settings->blockerThreshold($market->quote_asset));
            if (! $topAsk) {
                return [0.0, 0.0];
            }

            $orderPrice = (float) $topAsk->price - $market->minPriceIncrement();
        } else {
            $topBid = $book->firstBidWithMinNotional((float) $this->settings->blockerThreshold($market->quote_asset));
            if (! $topBid) {
                return [0.0, 0.0];
            }

            $orderPrice = (float) $topBid->price + $market->minPriceIncrement();
        }

        $availableBudget = $this->marketBudgets->availableForEntry($market, $direction);
        $tradeBudget = $availableBudget * ((float) $this->settings->decimal('trade_balance_percent') / 100);

        if ($direction === Deal::DIRECTION_SHORT) {
            return [$orderPrice, $tradeBudget];
        }

        $amount = $orderPrice > 0 ? $tradeBudget / $orderPrice : 0;

        return [$orderPrice, $amount];
    }

    protected function openDeal(
        Market $market,
        string $direction,
        string $mode,
        mixed $placed,
        float $orderPrice,
        float $amount,
        string $clientId,
        string $entrySide,
    ): TradingOrder {
        return DB::transaction(function () use ($market, $direction, $mode, $placed, $orderPrice, $amount, $clientId, $entrySide): TradingOrder {
            $deal = Deal::query()->create([
                'market_id' => $market->id,
                'mode' => $mode,
                'direction' => $direction,
                'status' => 'opening',
                'opened_at' => now(),
            ]);

            $order = TradingOrder::query()->create([
                'market_id' => $market->id,
                'deal_id' => $deal->id,
                'exchange' => $market->exchange,
                'symbol' => $market->symbol,
                'client_id' => $clientId,
                'external_id' => $placed->externalId,
                'mode' => $mode,
                'side' => $entrySide,
                'type' => 'limit',
                'status' => $placed->status,
                'price' => number_format($orderPrice, $market->tick_size, '.', ''),
                'amount' => number_format($amount, $market->step_size, '.', ''),
                'quote_amount' => number_format($orderPrice * $amount, 12, '.', ''),
                'tick_offset' => (int) $market->tick_size,
                'filled_amount' => $placed->status === 'filled'
                    ? (EntryOrderPayload::filledAmount($placed->raw) ?? '0')
                    : '0',
                'metadata' => $placed->raw,
            ]);

            if ($placed->status === 'filled' && ! $order->trades()->where('side', $entrySide)->exists()) {
                $averagePrice = EntryOrderPayload::averagePrice($placed->raw);
                $filledAmount = EntryOrderPayload::filledAmount($placed->raw);

                if ($averagePrice !== null && $filledAmount !== null) {
                    $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $placed->raw);
                } else {
                    Log::warning('Immediate fill could not be recorded: missing executedQty or average price in exchange payload.', [
                        'order_id' => $order->id,
                        'client_id' => $clientId,
                    ]);
                }
            }

            return $order;
        });
    }
}
