<?php

namespace App\Domain\Trading\Services;

use App\Models\Deal;
use App\Models\Trade;
use App\Models\TradingOrder;
use Illuminate\Support\Carbon;

class TradeRecorder
{
    public function recordFilledOrder(TradingOrder $order, string $averagePrice, string $filledAmount): Trade
    {
        $trade = Trade::query()->firstOrCreate(
            ['order_id' => $order->id, 'side' => $order->side],
            [
                'market_id' => $order->market_id,
                'deal_id' => $order->deal_id,
                'exchange_trade_id' => $order->external_id,
                'mode' => $order->mode,
                'price' => $averagePrice,
                'amount' => $filledAmount,
                'quote_amount' => number_format((float) $averagePrice * (float) $filledAmount, 12, '.', ''),
                'fee' => '0',
                'filled_at' => Carbon::now(),
                'metadata' => ['source' => 'order_status'],
            ],
        );

        $order->forceFill([
            'status' => 'filled',
            'filled_amount' => $filledAmount,
            'last_checked_at' => Carbon::now(),
        ])->save();

        $this->recalculateDeal($order->deal()->first());

        return $trade;
    }

    public function recalculateDeal(?Deal $deal): void
    {
        if (! $deal) {
            return;
        }

        $entries = $deal->trades()->where('side', 'buy')->get();
        $exits = $deal->trades()->where('side', 'sell')->get();

        $entryAmount = (float) $entries->sum(fn (Trade $trade): float => (float) $trade->amount);
        $entryQuote = (float) $entries->sum(fn (Trade $trade): float => (float) $trade->quote_amount);
        $exitAmount = (float) $exits->sum(fn (Trade $trade): float => (float) $trade->amount);
        $exitQuote = (float) $exits->sum(fn (Trade $trade): float => (float) $trade->quote_amount);

        $entryAverage = $entryAmount > 0 ? $entryQuote / $entryAmount : 0;
        $exitAverage = $exitAmount > 0 ? $exitQuote / $exitAmount : 0;
        $realizedCost = $entryAverage * $exitAmount;
        $pnl = $exitQuote - $realizedCost;
        $pnlPercent = $realizedCost > 0 ? ($pnl / $realizedCost) * 100 : 0;

        $status = $deal->status;
        if ($entryAmount > 0 && $exitAmount <= 0) {
            $status = 'entered';
        }
        if ($exitAmount > 0 && $exitAmount < $entryAmount) {
            $status = 'exiting';
        }
        if ($entryAmount > 0 && $exitAmount >= $entryAmount) {
            $status = 'closed';
        }

        $deal->forceFill([
            'status' => $status,
            'entry_average_price' => number_format($entryAverage, 12, '.', ''),
            'entry_amount' => number_format($entryAmount, 12, '.', ''),
            'exit_average_price' => number_format($exitAverage, 12, '.', ''),
            'exit_amount' => number_format($exitAmount, 12, '.', ''),
            'realized_pnl' => number_format($pnl, 12, '.', ''),
            'realized_pnl_percent' => number_format($pnlPercent, 8, '.', ''),
            'closed_at' => $status === 'closed' ? now() : $deal->closed_at,
        ])->save();
    }
}
