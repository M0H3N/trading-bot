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


        Cache::lock("trading:order:{$order->id}", (int) config('trading.lock_ttl'))->block(5, function () use ($order): void {

            $order->refresh();

            if (! in_array($order->status, ['pending', 'open', 'partially_filled'], true)) {
                return;
            }


            $client = $this->exchanges->client($order->exchange, $order->mode);
            $status = $client->getOrderStatus($order->client_id);

            $averagePrice = $status->averagePrice ?? EntryOrderPayload::averagePrice($status->raw);
            $filledAmount = EntryOrderPayload::filledAmount($status->raw);

            if ($averagePrice !== null && $filledAmount !== null)
                $this->tradeRecorder->recordFilledOrder($order, $averagePrice, $filledAmount, $status->raw);


            $order->forceFill([
                'status' => $status->status,
                'filled_amount' => $status->filledAmount ?? 0,
                'last_checked_at' => now(),
                'metadata' => array_merge($order->metadata ?? [], ['last_status' => $status->raw]),
            ])->save();

            if($status->isFilled())
                return;

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

            if (! $opportunityGone) {
                $this->marketEvaluation->evaluate($order->market()->firstOrFail());
            }
        });
    }
}
