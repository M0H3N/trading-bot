<?php

namespace App\Domain\Trading\Services;

use App\Models\Trade;
use App\Models\TradingOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SelfTradeService
{
    public function resolveBuyEntryAmount(TradingOrder $buyOrder, float $grossAmount, float $price): float
    {
        if ($buyOrder->side !== 'buy' || $grossAmount <= 0) {
            return $grossAmount;
        }

        $remaining = $grossAmount;
        $windowStart = now()->subSeconds($this->matchWindowSeconds());

        $sellTrades = Trade::query()
            ->where('market_id', $buyOrder->market_id)
            ->where('mode', $buyOrder->mode)
            ->where('side', 'sell')
            ->where('deal_id', '!=', $buyOrder->deal_id)
            ->where('filled_at', '>=', $windowStart)
            ->orderBy('filled_at')
            ->get();

        foreach ($sellTrades as $sellTrade) {
            if ($remaining <= 0) {
                break;
            }

            if (! $this->pricesMatch((float) $sellTrade->price, $price)) {
                continue;
            }

            $alreadyMatched = (float) Arr::get($sellTrade->metadata, 'self_trade_matched_amount', 0);
            $available = max(0.0, (float) $sellTrade->amount - $alreadyMatched);

            if ($available <= 0) {
                continue;
            }

            $matched = min($remaining, $available);
            $remaining -= $matched;

            $sellTrade->forceFill([
                'metadata' => array_merge($sellTrade->metadata ?? [], [
                    'self_trade_matched_amount' => number_format($alreadyMatched + $matched, 12, '.', ''),
                    'self_trade_buy_deal_id' => $buyOrder->deal_id,
                ]),
            ])->save();
        }

        $excluded = $grossAmount - $remaining;

        if ($excluded > 0) {
            Log::info('Self-trade quantity excluded from buy entry.', [
                'deal_id' => $buyOrder->deal_id,
                'order_id' => $buyOrder->id,
                'gross_amount' => $grossAmount,
                'excluded_amount' => $excluded,
                'net_entry_amount' => $remaining,
            ]);
        }

        return max(0.0, $remaining);
    }

    /**
     * @return list<int>
     */
    public function reconcileAfterSellTrade(Trade $sellTrade): array
    {
        if ($sellTrade->side !== 'sell') {
            return [];
        }

        $alreadyMatched = (float) Arr::get($sellTrade->metadata, 'self_trade_matched_amount', 0);
        $unmatched = max(0.0, (float) $sellTrade->amount - $alreadyMatched);

        if ($unmatched <= 0) {
            return [];
        }

        $affectedDealIds = [];
        $filledAt = $sellTrade->filled_at ?? now();
        $windowStart = $filledAt->copy()->subSeconds($this->matchWindowSeconds());
        $windowEnd = $filledAt->copy()->addSeconds($this->matchWindowSeconds());

        $buyTrades = Trade::query()
            ->where('market_id', $sellTrade->market_id)
            ->where('mode', $sellTrade->mode)
            ->where('side', 'buy')
            ->where('deal_id', '!=', $sellTrade->deal_id)
            ->where('filled_at', '>=', $windowStart)
            ->where('filled_at', '<=', $windowEnd)
            ->orderByDesc('filled_at')
            ->get();

        foreach ($buyTrades as $buyTrade) {
            if ($unmatched <= 0) {
                break;
            }

            if (! $this->pricesMatch((float) $buyTrade->price, (float) $sellTrade->price)) {
                continue;
            }

            $reducible = (float) $buyTrade->amount;

            if ($reducible <= 0) {
                continue;
            }

            $reduceBy = min($unmatched, $reducible);
            $ratio = $reduceBy / $reducible;

            $buyTrade->forceFill([
                'amount' => number_format($reducible - $reduceBy, 12, '.', ''),
                'quote_amount' => number_format((float) $buyTrade->quote_amount * (1 - $ratio), 12, '.', ''),
                'fee' => number_format((float) $buyTrade->fee * (1 - $ratio), 12, '.', ''),
                'metadata' => array_merge($buyTrade->metadata ?? [], [
                    'self_trade_excluded' => number_format(
                        (float) Arr::get($buyTrade->metadata, 'self_trade_excluded', 0) + $reduceBy,
                        12,
                        '.',
                        '',
                    ),
                    'self_trade_sell_deal_id' => $sellTrade->deal_id,
                ]),
            ])->save();

            $unmatched -= $reduceBy;
            $alreadyMatched += $reduceBy;
            $affectedDealIds[] = $buyTrade->deal_id;

            Log::info('Self-trade quantity removed from prior buy entry.', [
                'buy_deal_id' => $buyTrade->deal_id,
                'sell_deal_id' => $sellTrade->deal_id,
                'reduced_amount' => $reduceBy,
            ]);
        }

        if ($alreadyMatched > (float) Arr::get($sellTrade->metadata, 'self_trade_matched_amount', 0)) {
            $sellTrade->forceFill([
                'metadata' => array_merge($sellTrade->metadata ?? [], [
                    'self_trade_matched_amount' => number_format($alreadyMatched, 12, '.', ''),
                ]),
            ])->save();
        }

        return array_values(array_unique($affectedDealIds));
    }

    protected function pricesMatch(float $left, float $right): bool
    {
        if ($left <= 0 || $right <= 0) {
            return false;
        }

        return abs($left - $right) / max($left, $right) <= 0.001;
    }

    protected function matchWindowSeconds(): int
    {
        return (int) config('trading.self_trade_match_window', 120);
    }
}
