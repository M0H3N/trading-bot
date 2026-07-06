<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\DTO\PlaceOrderData;
use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
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

            $activeExit = $deal->orders()->exit()->active()->latest()->first();
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

        $currentPercent = (float) ($order->metadata['exit_percent'] ?? $this->settings->decimal('initial_exit_percent'));
        $nextPercent = max((float) $this->settings->decimal('minimum_exit_percent'), $currentPercent - (float) $this->settings->decimal('exit_step_percent'));
        $desiredPrice = (float) $deal->entry_average_price * (1 + ($nextPercent / 100));
        $stopLoss = abs($desiredPrice - (float) $fair->price) / (float) $fair->price * 100 >= (float) $this->settings->decimal('stop_loss_percent');

        if ($stopLoss) {
            $deal->forceFill(['status' => 'stop_loss'])->save();
            $desiredPrice = (float) $topAsk->price;
            $nextPercent = 0.0;
        } elseif ($nextPercent <= (float) $this->settings->decimal('exit_top_ask_from_percent') && $topAsk) {
            $desiredPrice = (float) $topAsk->price;
        }

        $client->cancelOrder($order->client_id);
        $order->forceFill(['status' => 'cancelled'])->save();

        $this->placeExitOrder($deal, $deal->remainingAmount(), $nextPercent, number_format($desiredPrice, $market->tick_size, '.', ''));
    }

    protected function placeExitOrder(Deal $deal, float $amount, float $exitPercent, ?string $forcedPrice = null): ?TradingOrder
    {
        $market = $deal->market()->firstOrFail();
        $price = $forcedPrice ?? number_format((float) $deal->entry_average_price * (1 + ($exitPercent / 100)), $market->tick_size, '.', '');
        $client = $this->exchanges->client($market->exchange, $deal->mode);

        if ($deal->mode === 'live') {
            $available = (float) $client->getBalance($market->base_asset)->available;
            $amount = min($amount, $available);
        }

        $formattedAmount = $this->floorAmount($amount, $market->step_size);

        if ($deal->mode === 'live') {
            if ((float) $formattedAmount > $available) {
                $this->markInsufficientBalance($deal, $market, (float) $formattedAmount, $available);

                return null;
            }
        }

        $clientId = $this->clientIds->make($market, 'sell');

        $clientId = 'Deal-'.$deal->id.'-'.$clientId;

        $placed = $client->placeOrder(new PlaceOrderData(
            symbol: $market->symbol,
            side: 'sell',
            type: 'limit',
            price: $price,
            amount: $formattedAmount,
            clientId: $clientId,
            mode: $deal->mode,
        ));

        $deal->forceFill(['status' => $deal->status === 'stop_loss' ? 'stop_loss' : 'exiting'])->save();

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => $market->exchange,
            'symbol' => $market->symbol,
            'client_id' => $clientId,
            'external_id' => $placed->externalId,
            'mode' => $deal->mode,
            'side' => 'sell',
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

        if ($placed->status === 'filled' && ! $order->trades()->where('side', 'sell')->exists()) {
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

    protected function markInsufficientBalance(Deal $deal, Market $market, float $requestedAmount, float $available): void
    {
        if ($deal->status === 'insufficient_balance') {
            return;
        }

        Log::warning('Deal marked insufficient_balance: exit amount exceeds wallet balance.', [
            'deal_id' => $deal->id,
            'symbol' => $market->symbol,
            'requested_amount' => $requestedAmount,
            'available_balance' => $available,
        ]);

        $deal->forceFill([
            'status' => 'insufficient_balance',
            'metadata' => array_merge($deal->metadata ?? [], [
                'insufficient_balance' => [
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
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();

        $this->tradeRecorder->recalculateDeal($deal->refresh());
    }
}
