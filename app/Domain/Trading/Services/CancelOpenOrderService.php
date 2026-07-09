<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\ExchangeManager;
use App\Models\TradingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelOpenOrderService
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradeRecorder $tradeRecorder,
    ) {}

    public function cancel(TradingOrder $order): void
    {
        if (! in_array($order->status, ['pending', 'open', 'partially_filled'], true)) {
            return;
        }

        Cache::lock("trading:order:{$order->id}", (int) config('trading.lock_ttl'))->block(5, function () use ($order): void {
            $order->refresh();

            if (! in_array($order->status, ['pending', 'open', 'partially_filled'], true)) {
                return;
            }

            $this->cancelOnExchange($order);
        });
    }

    public function cancelAllActive(): void
    {
        $orderIds = TradingOrder::query()->active()->pluck('id');

        foreach ($orderIds as $orderId) {
            $order = TradingOrder::query()->find($orderId);

            if ($order) {
                $this->cancel($order);
            }
        }
    }

    protected function cancelOnExchange(TradingOrder $order): void
    {
        $client = $this->exchanges->client($order->exchange, $order->mode);

        try {
            $client->cancelOrder($order->client_id);
        } catch (Throwable $exception) {
            Log::warning('Failed to cancel open order.', [
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
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

            if ($order->deal_id) {
                $this->tradeRecorder->recalculateDeal($order->deal()->first());
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to refresh open order status after cancel.', [
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
