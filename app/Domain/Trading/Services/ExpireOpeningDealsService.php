<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
use App\Models\TradingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireOpeningDealsService
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradingSettingsService $settings,
    ) {}

    public function expire(): void
    {
        Deal::query()
            ->where('status', 'opening')
            ->pluck('id')
            ->each(fn (int $dealId) => $this->expireDeal($dealId));
    }

    public function expireAbandonedOpeningDeal(int $dealId): void
    {
        $this->expireDeal($dealId);
    }

    protected function expireDeal(int $dealId): void
    {
        Cache::lock("trading:deal:{$dealId}", (int) config('trading.lock_ttl'))->block(5, function () use ($dealId): void {
            $deal = Deal::query()->find($dealId);

            if (! $deal || $deal->status !== 'opening') {
                return;
            }

            if (! $this->settings->marketEvaluationEnabled()) {
                $deal->orders()
                    ->entry()
                    ->active()
                    ->each(fn (TradingOrder $order) => $this->cancelBuyOrder($order));

                $deal->refresh();
            }

            if ($this->hasBlockingEntryOrders($deal)) {
                return;
            }

            $deal->forceFill([
                'status' => 'expired',
                'closed_at' => now(),
            ])->save();
        });
    }

    protected function hasBlockingEntryOrders(Deal $deal): bool
    {
        if ($deal->trades()->where('side', 'buy')->exists()) {
            return true;
        }

        if ($deal->orders()->entry()->active()->exists()) {
            return true;
        }

        return $deal->orders()
            ->entry()
            ->where('status', 'filled')
            ->whereDoesntHave('trades', fn ($trades) => $trades->where('side', 'buy'))
            ->exists();
    }

    protected function cancelBuyOrder(TradingOrder $order): void
    {
        $client = $this->exchanges->client($order->exchange, $order->mode);

        try {
            $client->cancelOrder($order->client_id);
        } catch (Throwable $exception) {
            Log::warning('Failed to cancel buy order while expiring opening deal.', [
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $status = $client->getOrderStatus($order->client_id);

            $order->forceFill([
                'status' => $status->status,
                'filled_amount' => $status->filledAmount ?? 0,
                'last_checked_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Failed to refresh buy order status while expiring opening deal.', [
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
