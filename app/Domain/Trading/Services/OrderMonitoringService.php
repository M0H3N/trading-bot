<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\ExchangeManager;
use App\Models\TradingOrder;
use Illuminate\Support\Facades\Cache;

class OrderMonitoringService
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradingSettingsService $settings,
        private readonly OrderBookPricingService $pricing,
        private readonly MarketEvaluationService $marketEvaluation,
        private readonly TradeRecorder $tradeRecorder,
        private readonly ExpireOpeningDealsService $expireOpeningDeals,
    ) {}

    public function monitor(TradingOrder $order): void
    {
        Cache::lock("trading:order:{$order->id}", (int) config('trading.lock_ttl'))->block(5, function () use ($order): void {
            $order->refresh();

            $isActive = in_array($order->status, ['pending', 'open', 'partially_filled'], true);
            $needsFillRecovery = $order->status === 'filled'
                && ! $order->trades()->where('side', $order->side)->exists();
            $needsDealStatusRecovery = $order->status === 'filled'
                && $order->deal_id
                && $order->deal()->where('status', 'opening')->exists();

            if (! $isActive && ! $needsFillRecovery && ! $needsDealStatusRecovery) {
                return;
            }

            if ($needsDealStatusRecovery && ! $isActive && ! $needsFillRecovery) {
                $this->tradeRecorder->recalculateDeal($order->deal()->first());

                return;
            }

            $client = $this->exchanges->client($order->exchange, $order->mode);
            $status = $client->getOrderStatus($order->client_id);

            $averagePrice = $status->averagePrice ?? EntryOrderPayload::averagePrice($status->raw);
            $filledAmount = EntryOrderPayload::filledAmount($status->raw) ?? $status->filledAmount;

            if ($averagePrice !== null && $filledAmount !== null) {
                $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $status->raw);
            }

            $order->forceFill([
                'status' => $status->status,
                'filled_amount' => $status->filledAmount ?? 0,
                'last_checked_at' => now(),
                'metadata' => array_merge($order->metadata ?? [], ['last_status' => $status->raw]),
            ])->save();

            if ($order->deal_id && $status->isFilled()) {
                $this->tradeRecorder->recalculateDeal($order->deal()->first());
            }

            if (! $isActive || $status->isFilled()) {
                return;
            }

            $market = $order->market()->firstOrFail();
            $deal = $order->deal()->firstOrFail();
            $book = $client->getOrderBook($order->symbol);
            $fair = $client->getFairPrice($order->symbol);
            $depthUsd = $this->settings->decimal('depth_usd');
            $usdtTmnPrice = $market->quote_asset === 'TMN'
                ? $this->pricing->averagePriceOfDepth(
                    $client->getOrderBook('USDTTMN'),
                    'bids',
                    $depthUsd,
                    'USDT',
                    depthInBaseAsset: true,
                )
                : null;

            if ($deal->isShort()) {
                $averageWallex = $this->pricing->averagePriceOfDepth($book, 'bids', $depthUsd, $market->quote_asset, $usdtTmnPrice);
                $blocked = $this->pricing->hasAnyOrderBelow(
                    $book,
                    (string) $order->price,
                    $market->quote_asset,
                    $this->settings->blockerThreshold($market->quote_asset),
                );
            } else {
                $averageWallex = $this->pricing->averagePriceOfDepth($book, 'asks', $depthUsd, $market->quote_asset, $usdtTmnPrice);
                $blocked = $this->pricing->hasAnyOrderAbove(
                    $book,
                    (string) $order->price,
                    $market->quote_asset,
                    $this->settings->blockerThreshold($market->quote_asset),
                );
            }

            $diff = $this->pricing->percentDifference($averageWallex, $fair->price);
            $opportunityGone = (float) $diff < (float) $this->settings->decimal('entry_threshold_percent');

            if (! $opportunityGone && ! $blocked) {
                return;
            }

            $client->cancelOrder($order->client_id);
            $order->forceFill(['status' => 'cancelled'])->save();

            if ($order->deal_id) {
                $this->expireOpeningDeals->expireAbandonedOpeningDeal($order->deal_id);
            }

            if (! $opportunityGone) {
                $this->marketEvaluation->evaluate($market);
            }
        });
    }
}
