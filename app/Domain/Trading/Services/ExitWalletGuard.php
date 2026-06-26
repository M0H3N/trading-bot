<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
use App\Models\Market;
use Illuminate\Support\Facades\Log;

class ExitWalletGuard
{
    public function __construct(
        private readonly ExchangeManager $exchanges,
    ) {}

    public function canPlaceExit(Deal $deal, float $amount): bool
    {
        if ($deal->mode !== 'live' || $amount <= 0) {
            return true;
        }

        $market = $deal->market ?? $deal->market()->firstOrFail();
        $client = $this->exchanges->client($market->exchange, $deal->mode);
        $available = (float) $client->getBalance($market->base_asset)->available;
        $dealRemaining = $deal->remainingAmount();
        $totalOpenRemaining = $this->totalOpenRemaining($market, $deal->mode);

        if ($totalOpenRemaining > $available + $this->tolerance()) {
            Log::critical('Exit blocked: sum of open deal remainings exceeds wallet balance.', [
                'deal_id' => $deal->id,
                'symbol' => $market->symbol,
                'total_open_remaining' => $totalOpenRemaining,
                'available_balance' => $available,
                'overbooked_by' => $totalOpenRemaining - $available,
            ]);

            return false;
        }

        if ($dealRemaining > $available + $this->tolerance()) {
            Log::critical('Exit blocked: deal remaining exceeds available wallet balance.', [
                'deal_id' => $deal->id,
                'symbol' => $market->symbol,
                'deal_remaining' => $dealRemaining,
                'available_balance' => $available,
            ]);

            return false;
        }

        if ($amount > $available + $this->tolerance()) {
            Log::critical('Exit blocked: requested sell amount exceeds available wallet balance.', [
                'deal_id' => $deal->id,
                'symbol' => $market->symbol,
                'requested_amount' => $amount,
                'available_balance' => $available,
            ]);

            return false;
        }

        return true;
    }

    protected function totalOpenRemaining(Market $market, string $mode): float
    {
        return (float) Deal::query()
            ->open()
            ->where('market_id', $market->id)
            ->where('mode', $mode)
            ->get()
            ->sum(fn (Deal $openDeal): float => $openDeal->remainingAmount());
    }

    protected function tolerance(): float
    {
        return (float) config('trading.wallet_guard_tolerance', 1);
    }
}
