<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\DTO\PlaceOrderData;
use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExitManagementService
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradingSettingsService $settings,
        private readonly ClientOrderIdFactory $clientIds,
        private readonly TradeRecorder $tradeRecorder,
    ) {}

    public function manage(Deal $deal): void
    {
        if (! $this->settings->exitManagementEnabled() || ! in_array($deal->status, ['entered', 'exiting', 'stop_loss'], true)) {
            return;
        }

        Cache::lock("trading:deal:{$deal->id}", (int) config('trading.lock_ttl'))->block(5, function () use ($deal): void {
            $deal->refresh();

            if ($deal->hasActiveEntryOrder()) {
                return;
            }

            $remaining = $deal->remainingAmount();

            if ($this->closeDealIfRemainderTooSmall($deal)) {
                $this->closeDeal($deal);

                return;
            }

            $activeExit = $deal->orders()
                ->where('side', $deal->exitSide())
                ->active()
                ->latest()
                ->first();

            if ($activeExit) {
                $this->monitorExitOrder($activeExit);

                return;
            }

            $this->placeExitOrder($deal, $remaining, (float) $this->settings->decimal('initial_exit_percent'));

            $deal->refresh();

            if ($this->closeDealIfRemainderTooSmall($deal)) {
                $this->closeDeal($deal);
            }
        });
    }

    protected function monitorExitOrder(TradingOrder $order): void
    {
        $client = $this->exchanges->client($order->exchange, $order->mode);
        $status = $client->getOrderStatus($order->client_id);

        $averagePrice = $status->averagePrice ?? EntryOrderPayload::averagePrice($status->raw);
        $filledAmount = EntryOrderPayload::filledAmount($status->raw);

        if ($averagePrice !== null && $filledAmount !== null) {
            $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $status->raw);
        }

        $order->forceFill([
            'status' => $status->status,
            'filled_amount' => $status->filledAmount ?? 0,
            'last_checked_at' => now(),
        ])->save();

        if ($status->isFilled()) {
            return;
        }

        if ($order->created_at && $order->created_at->diffInSeconds(now()) < (int) config('trading.exit_interval', 30)) {
            return;
        }

        $deal = $order->deal()->firstOrFail();
        $market = $order->market()->firstOrFail();
        $book = $client->getOrderBook($order->symbol);
        $fair = $client->getFairPrice($order->symbol);
        $topAsk = $book->topAsk();

        if ($deal->status === 'stop_loss' && $topAsk) {
            $tickSize = (int) $market->tick_size;
            $orderPrice = number_format((float) $order->price, $tickSize, '.', '');
            $topAskPrice = number_format((float) $topAsk->price, $tickSize, '.', '');

            if ($orderPrice === $topAskPrice) {
                return;
            }
        }

        $floorPercent = (float) $this->settings->decimal('exit_top_ask_from_percent');
        $currentPercent = (float) ($order->metadata['exit_percent'] ?? $this->settings->decimal('initial_exit_percent'));
        $nextPercent = max($floorPercent, $currentPercent - (float) $this->settings->decimal('exit_step_percent'));
        $desiredPrice = $this->exitPriceFromPercent($deal, $nextPercent);
        $stopLoss = abs($desiredPrice - (float) $fair->price) / (float) $fair->price * 100 >= (float) $this->settings->decimal('stop_loss_percent');

        if ($stopLoss) {
            $deal->forceFill(['status' => 'stop_loss'])->save();
            $desiredPrice = (float) $topAsk->price;
            $nextPercent = 0.0;
        }

        $replacementPrice = number_format($desiredPrice, $market->tick_size, '.', '');

        $this->withAssetLock($market, $deal, function () use ($client, $deal, $market, $order, $nextPercent, $replacementPrice): void {
            $prepared = $this->prepareExitAmount($deal, $market, $deal->remainingAmount(), $replacementPrice, allowInsufficientMark: false);

            if ($prepared === null) {
                return;
            }

            $client->cancelOrder($order->client_id);
            $order->forceFill(['status' => 'cancelled'])->save();

            $this->submitExitOrder($deal, $market, $prepared, $nextPercent, $replacementPrice);
        });
    }

    protected function placeExitOrder(Deal $deal, float $amount, float $exitPercent, ?string $forcedPrice = null): ?TradingOrder
    {
        $market = $deal->market()->firstOrFail();
        $price = $forcedPrice ?? $this->formatExitPrice($deal, $market, $exitPercent);

        return $this->withAssetLock($market, $deal, function () use ($deal, $market, $amount, $exitPercent, $price): ?TradingOrder {
            $prepared = $this->prepareExitAmount($deal, $market, $amount, $price);

            if ($prepared === null) {
                return null;
            }

            return $this->submitExitOrder($deal, $market, $prepared, $exitPercent, $price);
        });
    }

    /**
     * @return array{amount: string, available: float}|null
     */
    protected function prepareExitAmount(
        Deal $deal,
        Market $market,
        float $amount,
        string $price,
        bool $allowInsufficientMark = true,
    ): ?array {
        $available = null;
        $requestedAmount = $amount;

        if ($deal->mode === 'live') {
            $client = $this->exchanges->client($market->exchange, $deal->mode);

            if ($deal->isShort()) {
                $available = (float) $client->getBalance($market->quote_asset)->available;
                $maxAffordable = (float) $price > 0 ? $available / (float) $price : 0.0;
                $amount = min($amount, $maxAffordable);
            } else {
                $available = (float) $client->getBalance($market->base_asset)->available;
                $amount = min($amount, $available);
            }
        }

        $formattedAmount = $this->floorAmount($amount, $market->step_size);

        if ($deal->mode === 'live') {
            if ((float) $formattedAmount <= 0) {
                return null;
            }

            if ((float) $price * (float) $formattedAmount < $this->settings->exitMinOrderSum($market->quote_asset)) {
                return null;
            }

            if ($deal->isShort()) {
                $requiredQuote = (float) $price * (float) $formattedAmount;

                if ($requiredQuote > (float) $available) {
                    if ($allowInsufficientMark) {
                        $this->markInsufficientBalance($deal, $market, $requiredQuote, (float) $available, 'quote');
                    }

                    return null;
                }
            } elseif ($requestedAmount > (float) $available) {
                if ($allowInsufficientMark) {
                    $this->markInsufficientBalance($deal, $market, $requestedAmount, (float) $available, 'base');
                }

                return null;
            } elseif ((float) $formattedAmount > (float) $available) {
                if ($allowInsufficientMark) {
                    $this->markInsufficientBalance($deal, $market, (float) $formattedAmount, (float) $available, 'base');
                }

                return null;
            }
        }

        return [
            'amount' => $formattedAmount,
            'available' => (float) ($available ?? 0),
        ];
    }

    /**
     * @param  array{amount: string, available: float}  $prepared
     */
    protected function submitExitOrder(Deal $deal, Market $market, array $prepared, float $exitPercent, string $price): ?TradingOrder
    {
        $client = $this->exchanges->client($market->exchange, $deal->mode);
        $formattedAmount = $prepared['amount'];
        $exitSide = $deal->exitSide();
        $clientId = 'Deal-'.$deal->id.'-'.$this->clientIds->make($market, $exitSide);

        try {
            $placed = $client->placeOrder(new PlaceOrderData(
                symbol: $market->symbol,
                side: $exitSide,
                type: 'limit',
                price: $price,
                amount: $formattedAmount,
                clientId: $clientId,
                mode: $deal->mode,
            ));
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 422 && $this->isInsufficientBalanceResponse($exception->response->json())) {
                $available = $deal->mode === 'live'
                    ? ($deal->isShort()
                        ? (float) $client->getBalance($market->quote_asset)->available
                        : (float) $client->getBalance($market->base_asset)->available)
                    : $prepared['available'];

                Log::warning('Exit placeOrder rejected with insufficient balance (422).', [
                    'deal_id' => $deal->id,
                    'symbol' => $market->symbol,
                    'requested_amount' => $formattedAmount,
                    'available_balance' => $available,
                ]);

                if ($deal->mode === 'live') {
                    if ($deal->isShort() && (float) $price * (float) $formattedAmount > $available) {
                        $this->markInsufficientBalance($deal, $market, (float) $price * (float) $formattedAmount, $available, 'quote');
                    } elseif (! $deal->isShort() && (float) $formattedAmount > $available) {
                        $this->markInsufficientBalance($deal, $market, (float) $formattedAmount, $available, 'base');
                    }
                }

                return null;
            }

            throw $exception;
        }

        $deal->forceFill(['status' => $deal->status === 'stop_loss' ? 'stop_loss' : 'exiting'])->save();

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => $market->exchange,
            'symbol' => $market->symbol,
            'client_id' => $clientId,
            'external_id' => $placed->externalId,
            'mode' => $deal->mode,
            'side' => $exitSide,
            'type' => 'limit',
            'status' => $placed->status,
            'price' => $price,
            'amount' => $formattedAmount,
            'quote_amount' => number_format((float) $price * (float) $formattedAmount, 12, '.', ''),
            'filled_amount' => $placed->status === 'filled'
                ? (EntryOrderPayload::filledAmount($placed->raw) ?? '0')
                : '0',
            'metadata' => array_merge($placed->raw, ['exit_percent' => $exitPercent]),
        ]);

        if ($placed->status === 'filled' && ! $order->trades()->where('side', $exitSide)->exists()) {
            $averagePrice = EntryOrderPayload::averagePrice($placed->raw);
            $filledAmount = EntryOrderPayload::filledAmount($placed->raw);

            if ($averagePrice !== null && $filledAmount !== null) {
                $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $placed->raw);
            } else {
                Log::warning('Immediate exit fill could not be recorded: missing executedQty or average price in exchange payload.', [
                    'order_id' => $order->id,
                    'client_id' => $clientId,
                ]);
            }
        }

        return $order;
    }

    protected function withAssetLock(Market $market, Deal $deal, callable $callback): mixed
    {
        $asset = $deal->isShort() ? $market->quote_asset : $market->base_asset;
        $key = "trading:asset:{$market->exchange}:{$asset}";

        return Cache::lock($key, (int) config('trading.lock_ttl'))->block(5, $callback);
    }

    protected function formatExitPrice(Deal $deal, Market $market, float $exitPercent): string
    {
        return number_format($this->exitPriceFromPercent($deal, $exitPercent), $market->tick_size, '.', '');
    }

    protected function exitPriceFromPercent(Deal $deal, float $exitPercent): float
    {
        $multiplier = $deal->isShort()
            ? (1 - ($exitPercent / 100))
            : (1 + ($exitPercent / 100));

        return (float) $deal->entry_average_price * $multiplier;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function isInsufficientBalanceResponse(?array $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $errorCodes = Arr::wrap(Arr::get($payload, 'result.error_code', []));

        return in_array(1006, array_map(intval(...), $errorCodes), true);
    }

    protected function markInsufficientBalance(Deal $deal, Market $market, float $requestedAmount, float $available, string $assetType = 'base'): void
    {
        if ($deal->status === 'insufficient_balance') {
            return;
        }

        Log::warning('Deal marked insufficient_balance: exit amount exceeds wallet balance.', [
            'deal_id' => $deal->id,
            'symbol' => $market->symbol,
            'asset_type' => $assetType,
            'requested_amount' => $requestedAmount,
            'available_balance' => $available,
        ]);

        $deal->forceFill([
            'status' => 'insufficient_balance',
            'metadata' => array_merge($deal->metadata ?? [], [
                'insufficient_balance' => [
                    'asset_type' => $assetType,
                    'requested_amount' => number_format($requestedAmount, 12, '.', ''),
                    'available_balance' => number_format($available, 12, '.', ''),
                    'recorded_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }

    protected function floorAmount(float|string $amount, mixed $stepSize): string
    {
        $precision = (int) $stepSize;
        $scale = max($precision + 4, 12);
        $amountStr = is_string($amount)
            ? $amount
            : number_format((float) $amount, $scale, '.', '');

        if ($precision === 0) {
            return bcadd($amountStr, '0', 0);
        }

        $factor = bcpow('10', (string) $precision, 0);

        return bcdiv(bcmul($amountStr, $factor, 0), $factor, $precision);
    }

    protected function closeDealIfRemainderTooSmall(Deal $deal): bool
    {
        if ($deal->hasActiveEntryOrder()) {
            return false;
        }

        $remaining = $deal->remainingAmount();

        if ($remaining <= 0) {
            return true;
        }

        $market = $deal->market()->firstOrFail();
        $price = (float) $deal->exit_average_price > 0
            ? (float) $deal->exit_average_price
            : (float) $deal->entry_average_price;
        $notional = $remaining * $price;

        if ($notional >= $this->settings->exitMinOrderSum($market->quote_asset)) {
            return false;
        }

        return true;
    }

    protected function closeDeal(Deal $deal): void
    {
        $deal->forceFill([
            'status' => $deal->status === 'stop_loss' ? 'stop_loss_closed' : 'closed',
            'closed_at' => now(),
        ])->save();

        $this->tradeRecorder->recalculateDeal($deal->refresh());
    }
}
