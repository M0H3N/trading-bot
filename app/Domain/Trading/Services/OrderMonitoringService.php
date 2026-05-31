<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\DTO\PlacedOrderData;
use App\Domain\Exchange\ExchangeManager;
use App\Models\TradingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OrderMonitoringService
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradingSettingsService $settings,
        private readonly OrderBookPricingService $pricing,
        private readonly MarketEvaluationService $marketEvaluation,
        private readonly TradeRecorder $tradeRecorder,
    ) {}

    public function monitor(TradingOrder $order): void
    {
        if (! $this->settings->botEnabled()) {
            return;
        }

        Cache::lock("trading:order:{$order->id}", (int) config('trading.lock_ttl'))->block(5, function () use ($order): void {
            $order->refresh();

            if ($this->recordEntryFillIfUnrecorded($order)) {
                return;
            }

            if (! in_array($order->status, ['pending', 'open', 'partially_filled'], true)) {
                return;
            }

            $client = $this->exchanges->client($order->exchange, $order->mode);
            $status = $client->getOrderStatus($order->client_id);

            if ($status->isFilled()) {
                $averagePrice = $status->averagePrice ?? EntryOrderPayload::averagePrice($status->raw);
                $filledAmount = EntryOrderPayload::filledAmount($status->raw);

                if ($averagePrice !== null && $filledAmount !== null) {
                    $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $status->raw);
                } else {
                    Log::warning('Filled entry could not be recorded: missing executedQty or average price in exchange payload.', [
                        'order_id' => $order->id,
                        'client_id' => $order->client_id,
                    ]);
                }

                return;
            }

            $order->forceFill([
                'status' => $status->status,
                'filled_amount' => $status->filledAmount,
                'last_checked_at' => now(),
                'metadata' => array_merge($order->metadata ?? [], ['last_status' => $status->raw]),
            ])->save();

            if ($order->side !== 'buy') {
                return;
            }

            $book = $client->getOrderBook($order->symbol);
            $fair = $client->getFairPrice($order->symbol);
            $usdt = $client->getFairPrice('USDTTMN');
            $averageWallex = $this->pricing->averagePriceOfDepth($book,'asks' ,$usdt->price, $this->settings->decimal('depth_usd'));
            $diff = $this->pricing->percentDifference($averageWallex, $fair->price);
            $opportunityGone = (float) $diff < (float) $this->settings->decimal('entry_threshold_percent');
            $blocked = $this->pricing->hasAnyOrderAbove($book, (string) $order->price, $this->settings->decimal('blocker_threshold_tmn'));

            if (! $opportunityGone && ! $blocked) {
                return;
            }

            $client->cancelOrder($order->client_id);
            $order->forceFill(['status' => 'cancelled'])->save();
            Log::info('Trading entry order cancelled for reprice.', ['order_id' => $order->id, 'blocked' => $blocked, 'opportunity_gone' => $opportunityGone]);

            if (! $opportunityGone) {
                $this->marketEvaluation->evaluate($order->market()->firstOrFail());
            }
        });
    }

    /**
     * When the exchange fills an entry order on placement, persist the trade and deal entry fields immediately.
     */
    public function recordEntryFillFromPlacement(TradingOrder $order, PlacedOrderData $placed): void
    {
        if ($placed->status !== 'filled' || ! $this->entryFillNeedsRecording($order)) {
            return;
        }

        $averagePrice = EntryOrderPayload::averagePrice($placed->raw);
        $filledAmount = EntryOrderPayload::filledAmount($placed->raw);

        if ($averagePrice === null || $filledAmount === null) {
            Log::warning('Placement fill could not be recorded: missing executedQty or average price in exchange payload.', [
                'order_id' => $order->id,
                'client_id' => $order->client_id,
            ]);

            return;
        }

        $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $placed->raw);
    }

    protected function recordEntryFillIfUnrecorded(TradingOrder $order): bool
    {
        if ($order->status !== 'filled' || ! $this->entryFillNeedsRecording($order)) {
            return false;
        }

        $client = $this->exchanges->client($order->exchange, $order->mode);
        $status = $client->getOrderStatus($order->client_id);

        if (! $status->isFilled()) {
            return false;
        }

        $averagePrice = $status->averagePrice ?? EntryOrderPayload::averagePrice($status->raw);
        $filledAmount = EntryOrderPayload::filledAmount($status->raw);

        if ($averagePrice === null || $filledAmount === null) {
            Log::warning('Filled entry could not be recorded: missing executedQty or average price in exchange payload.', [
                'order_id' => $order->id,
                'client_id' => $order->client_id,
            ]);

            return false;
        }

        $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $status->raw);

        return true;
    }

    protected function entryFillNeedsRecording(TradingOrder $order): bool
    {
        return $order->side === 'buy'
            && $order->deal_id !== null
            && ! $order->trades()->where('side', 'buy')->exists();
    }
}
