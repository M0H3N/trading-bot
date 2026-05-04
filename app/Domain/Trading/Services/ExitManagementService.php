<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\DTO\PlaceOrderData;
use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
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
        if (! $this->settings->botEnabled() || ! in_array($deal->status, ['entered', 'exiting', 'stop_loss'], true)) {
            return;
        }

        Cache::lock("trading:deal:{$deal->id}", (int) config('trading.lock_ttl'))->block(5, function () use ($deal): void {
            $deal->refresh();
            $remaining = $deal->remainingAmount();

            if ($remaining <= 0) {
                $this->tradeRecorder->recalculateDeal($deal);

                return;
            }

            $activeExit = $deal->orders()->active()->exit()->latest()->first();
            if ($activeExit) {
                $this->monitorExitOrder($activeExit);

                return;
            }

            $this->placeExitOrder($deal, $remaining, (float) $this->settings->decimal('initial_exit_percent'));
        });
    }

    protected function monitorExitOrder(TradingOrder $order): void
    {
        $client = $this->exchanges->client($order->exchange, $order->mode);
        $status = $client->getOrderStatus($order->client_id);

        if ($status->isFilled()) {
            $this->tradeRecorder->recordFilledOrder(
                $order,
                $status->averagePrice ?? (string) $order->price,
                $status->filledAmount,
            );

            return;
        }

        $order->forceFill([
            'status' => $status->status,
            'filled_amount' => $status->filledAmount,
            'last_checked_at' => now(),
        ])->save();

        if ($order->updated_at && $order->updated_at->diffInSeconds(now()) < (int) config('trading.exit_interval', 30)) {
            return;
        }

        $deal = $order->deal()->firstOrFail();
        $market = $order->market()->firstOrFail();
        $book = $client->getOrderBook($order->symbol);
        $fair = $client->getFairPrice($order->symbol);
        $topAsk = $book->topAsk();
        $currentPercent = (float) ($order->metadata['exit_percent'] ?? $this->settings->decimal('initial_exit_percent'));
        $nextPercent = max((float) $this->settings->decimal('minimum_exit_percent'), $currentPercent - (float) $this->settings->decimal('exit_step_percent'));
        $desiredPrice = (float) $deal->entry_average_price * (1 + ($nextPercent / 100));
        $stopLoss = abs($desiredPrice - (float) $fair->price) / (float) $fair->price * 100 >= (float) $this->settings->decimal('stop_loss_percent');

        if ($stopLoss) {
            $deal->forceFill(['status' => 'stop_loss'])->save();
            $desiredPrice = (float)$topAsk;
            $nextPercent = 0.0;
        } elseif ($nextPercent <= (float) $this->settings->decimal('exit_top_ask_from_percent') && $topAsk) {
            $desiredPrice = (float) $topAsk->price;
        }

        $client->cancelOrder($order->client_id);
        $order->forceFill(['status' => 'cancelled'])->save();

        $this->placeExitOrder($deal, $deal->remainingAmount(), $nextPercent, number_format($desiredPrice, $market->tick_size, '.', ''));
        Log::info('Trading exit order repriced.', ['deal_id' => $deal->id, 'stop_loss' => $stopLoss, 'market' => $market->symbol]);
    }

    protected function placeExitOrder(Deal $deal, float $amount, float $exitPercent, ?string $forcedPrice = null): TradingOrder
    {
        $market = $deal->market()->firstOrFail();
        $client = $this->exchanges->client($market->exchange, $deal->mode);
        $price = $forcedPrice ?? number_format((float) $deal->entry_average_price * (1 + ($exitPercent / 100)), $market->tick_size, '.', '');
        $clientId = $this->clientIds->make($market, 'sell');
        $placed = $client->placeOrder(new PlaceOrderData(
            symbol: $market->symbol,
            side: 'sell',
            type: 'limit',
            price: $price,
            amount: number_format($amount, $market->step_size, '.', ''),
            clientId: $clientId,
            mode: $deal->mode,
        ));

        $deal->forceFill(['status' => $deal->status === 'stop_loss' ? 'stop_loss' : 'exiting'])->save();

        return TradingOrder::query()->create([
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
            'amount' => number_format($amount, $market->step_size, '.', ''),
            'quote_amount' => number_format((float) $price * $amount, 12, '.', ''),
            'metadata' => array_merge($placed->raw, ['exit_percent' => $exitPercent]),
        ]);
    }
}
