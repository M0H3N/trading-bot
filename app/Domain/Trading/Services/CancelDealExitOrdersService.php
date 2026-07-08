<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
use App\Models\TradingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelDealExitOrdersService
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradeRecorder $tradeRecorder,
    ) {}

    public function cancelForDeal(int $dealId): void
    {
        Cache::lock("trading:deal:{$dealId}", (int) config('trading.lock_ttl'))->block(5, function () use ($dealId): void {
            $deal = Deal::query()->find($dealId);

            if (! $deal || $deal->status !== 'manually_closed') {
                return;
            }

            $deal->orders()
                ->where('side', $deal->exitSide())
                ->active()
                ->each(fn (TradingOrder $order) => $this->cancelExitOrder($order));

            $this->tradeRecorder->recalculateDeal($deal->refresh());
        });
    }

    protected function cancelExitOrder(TradingOrder $order): void
    {
        $client = $this->exchanges->client($order->exchange, $order->mode);

        try {
            $client->cancelOrder($order->client_id);
        } catch (Throwable $exception) {
            Log::warning('Failed to cancel exit order for manually closed deal.', [
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
        } catch (Throwable $exception) {
            Log::warning('Failed to refresh exit order status for manually closed deal.', [
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
