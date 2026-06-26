<?php

namespace App\Domain\Trading\Services;

use App\Models\Deal;
use App\Models\Trade;
use App\Models\TradingOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TradeRecorder
{
    public function __construct(
        private readonly SelfTradeService $selfTrades,
    ) {}

    /**
     * @param  array<string, mixed>  $exchangeRaw
     */
    public function recordFilledOrder(TradingOrder $order, string $averagePrice, string $filledAmount, array $exchangeRaw = []): ?Trade
    {
        $grossAmount = (float) $filledAmount;
        $entryAmount = $grossAmount;
        $metadata = ['source' => 'order_status'];

        if ($order->side === 'buy') {
            $entryAmount = $this->selfTrades->resolveBuyEntryAmount($order, $grossAmount, (float) $averagePrice);

            if ($entryAmount <= 0) {
                Log::info('Buy fill not recorded: quantity matched entirely as self-trade.', [
                    'deal_id' => $order->deal_id,
                    'order_id' => $order->id,
                    'gross_amount' => $grossAmount,
                ]);
                $this->recalculateDeal($order->deal()->first());

                return null;
            }

            if ($entryAmount < $grossAmount) {
                $metadata['gross_amount'] = number_format($grossAmount, 12, '.', '');
                $metadata['self_trade_excluded'] = number_format($grossAmount - $entryAmount, 12, '.', '');
            }
        }

        $fee = EntryOrderPayload::fee($exchangeRaw);
        $feeAsset = EntryOrderPayload::feeAsset($exchangeRaw);

        if ($order->side === 'buy' && $grossAmount > 0 && $entryAmount < $grossAmount) {
            $ratio = $entryAmount / $grossAmount;
            $fee = number_format((float) $fee * $ratio, 12, '.', '');
        }

        $filledAmount = number_format($entryAmount, 12, '.', '');

        $trade = Trade::query()->firstOrCreate(
            ['order_id' => $order->id, 'side' => $order->side],
            [
                'deal_id' => $order->deal_id,
                'amount' => $filledAmount,
                'price' => $averagePrice,
                'market_id' => $order->market_id,
                'exchange_trade_id' => $order->external_id,
                'mode' => $order->mode,
                'quote_amount' => number_format((float) $averagePrice * $entryAmount, 12, '.', ''),
                'fee' => $fee,
                'fee_asset' => $feeAsset,
                'filled_at' => Carbon::now(),
                'metadata' => $metadata,
            ],
        );

        if (! $trade->wasRecentlyCreated) {
            $trade->forceFill([
                'fee' => $fee,
                'fee_asset' => $feeAsset,
                'amount' => $filledAmount,
                'price' => $averagePrice,
                'quote_amount' => number_format((float) $averagePrice * $entryAmount, 12, '.', ''),
                'metadata' => array_merge($trade->metadata ?? [], $metadata),
            ])->save();
        }

        $this->recalculateDeal($order->deal()->first());

        if ($order->side === 'sell') {
            foreach ($this->selfTrades->reconcileAfterSellTrade($trade) as $dealId) {
                $this->recalculateDeal(Deal::query()->find($dealId));
            }
        }

        return $trade;
    }

    public function recalculateDeal(?Deal $deal): void
    {
        if (! $deal) {
            return;
        }

        $deal->loadMissing('trades');
        $market = $deal->market;

        $entries = $deal->trades->where('side', 'buy');
        $exits = $deal->trades->where('side', 'sell');

        $entryAmount = (float) $entries->sum(fn (Trade $trade): float => (float) $trade->amount);
        $entryQuote = (float) $entries->sum(fn (Trade $trade): float => (float) $trade->quote_amount);

        $exitAmount = (float) $exits->sum(fn (Trade $trade): float => (float) $trade->amount);
        $exitQuote = (float) $exits->sum(fn (Trade $trade): float => (float) $trade->quote_amount);

        $entryAverage = $entryAmount > 0 ? $entryQuote / $entryAmount : 0;
        $exitAverage = $exitAmount > 0 ? $exitQuote / $exitAmount : 0;

        $feeInQuote = (float) $deal->trades->sum(fn (Trade $trade): float => $this->feeInQuote($trade));

        $entryAmount = number_format($entryAmount, 12, '.', '');
        $exitAmount = number_format($exitAmount, 12, '.', '');

        $unexitedAmount = 0;
        $pnl = 0;
        $pnlPercent = 0;
        $exited = false;


        if ($deal->isClosed()) {
            if ($deal->status === 'closed' && (($entryAmount - $exitAmount) * $entryAverage) <= $this->minDiffEntryExit($market->quote_asset)) {
                $pnl = $exitQuote - $entryQuote - $feeInQuote;
                $pnlPercent = $entryQuote > 0 ? ($pnl / $entryQuote) * 100 : 0;
            }else{
                $unexitedAmount =  $entryAmount - $exitAmount;
                $exited = false;
                $pnl = 0;
                $pnlPercent = 0;
            }
        }

        $status = $deal->status;

        if ($deal->isClosed()) {
            $status = $deal->status;
        } elseif ($entryAmount > 0 && $exitAmount <= 0) {
            $status = 'entered';
        } elseif ($exitAmount > 0 && $exitAmount < $entryAmount) {
            $status = 'exiting';
        }

        $deal->forceFill([
            'status' => $status,
            'entry_average_price' => number_format($entryAverage, 12, '.', ''),
            'entry_amount' => number_format($entryAmount, 12, '.', ''),
            'exit_average_price' => number_format($exitAverage, 12, '.', ''),
            'exit_amount' => number_format($exitAmount, 12, '.', ''),
            'realized_pnl' => number_format($pnl, 12, '.', ''),
            'realized_pnl_percent' => number_format($pnlPercent, 8, '.', ''),
            'exited' => $exited,
            'unexited_amount' => number_format($unexitedAmount, 12, '.', ''),
            'closed_at' => $deal->isClosed() ? ($deal->closed_at ?? now()) : $deal->closed_at,
        ])->save();
    }

    protected function feeInQuote(Trade $trade): float
    {
        $fee = (float) $trade->fee;

        if ($fee <= 0) {
            return 0.0;
        }

        if ($trade->side === 'buy') {
            return $fee * (float) $trade->price;
        }

        return $fee;
    }

    protected function minDiffEntryExit(string $quoteAsset): float
    {
        return match (strtoupper($quoteAsset)) {
            'USDT' => (float) '1',
            default => (float) '50000',
        };
    }
}
