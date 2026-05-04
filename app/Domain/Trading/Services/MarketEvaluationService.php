<?php

namespace App\Domain\Trading\Services;

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
    ) {}

    public function evaluate(Market $market): ?TradingOrder
    {

        if (! $market->is_active || ! $this->settings->botEnabled()) {
            return null;
        }

        return Cache::lock("trading:market:{$market->id}", (int) config('trading.lock_ttl'))->block(5, function () use ($market): ?TradingOrder {
            if ($market->orders()->active()->entry()->exists()) {
                return null;
            }


            $mode = $this->settings->mode();
            $client = $this->exchanges->client($market->exchange, $mode);
            $fair = $client->getFairPrice($market->symbol);
            $book = $client->getOrderBook($market->symbol);
            $usdt = $client->getFairPrice('USDTTMN');
            $averageWallex = $this->pricing->averagePriceOfDepth($book,'asks' ,$usdt->price, $this->settings->decimal('depth_usd'));

            $diff = $this->pricing->percentDifference($averageWallex, $fair->price);


            if ((float) $diff < (float) $this->settings->decimal('entry_threshold_percent')) {
                Log::info('Trading opportunity skipped.', compact('diff') + ['symbol' => $market->symbol]);
                return null;
            }

            $topAsk = $book->topAsk();
            if (! $topAsk) {
                return null;
            }

            $orderPrice = (float) $topAsk->price *  (float)$this->settings->decimal('tick_offset');
            $balance = $client->getBalance($market->quote_asset);


            $budget = ((float) $balance->available) * ((float) $this->settings->decimal('trade_balance_percent') / 100);
            $amount = $orderPrice > 0 ? $budget / $orderPrice : 0;

            if ($amount <= 0) {
                return null;
            }

            $clientId = $this->clientIds->make($market, 'buy');
            $placed = $client->placeOrder(new PlaceOrderData(
                symbol: $market->symbol,
                side: 'buy',
                type: 'limit',
                price: number_format($orderPrice, $market->tick_size, '.', ''),
                amount: number_format($amount, $market->step_size, '.', ''),
                clientId: $clientId,
                mode: $mode,
            ));

            return DB::transaction(function () use ($market, $mode, $placed, $orderPrice, $amount, $clientId): TradingOrder {
                $deal = Deal::query()->create([
                    'market_id' => $market->id,
                    'mode' => $mode,
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
                    'side' => 'buy',
                    'type' => 'limit',
                    'status' => $placed->status,
                    'price' => number_format($orderPrice, $market->tick_size, '.', ''),
                    'amount' => number_format($amount, $market->step_size, '.', ''),
                    'quote_amount' => number_format($orderPrice * $amount, 12, '.', ''),
                    'tick_offset' => $this->settings->int('tick_offset'),
                    'metadata' => $placed->raw,
                ]);

                Log::info('Trading entry order placed.', ['order_id' => $order->id, 'client_id' => $clientId]);

                return $order;
            });
        });
    }
}
