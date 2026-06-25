<?php

namespace App\Domain\Trading\Services;

use App\Infrastructure\Exchange\Wallex\WallexClient;
use App\Models\Market;
use Illuminate\Support\Arr;

class MarketSyncService
{
    public function __construct(
        private readonly WallexClient $wallexClient,
    ) {}

    /**
     * @return array{updated: int, skipped: int, missing: int}
     */
    public function sync(): array
    {
        $apiMarkets = $this->wallexClient->getAllMarkets();
        $updated = 0;
        $skipped = 0;
        $missing = 0;

        Market::query()
            ->where('exchange', 'wallex')
            ->each(function (Market $market) use ($apiMarkets, &$updated, &$skipped, &$missing): void {
                $exchangeMarket = Arr::get($apiMarkets, "{$market->symbol}.EXCHANGE");

                if (! is_array($exchangeMarket)) {
                    $missing++;

                    return;
                }

                $updates = [];

                $tickSize = Arr::get($exchangeMarket, 'tickSize');
                $stepSize = Arr::get($exchangeMarket, 'stepSize');

                if ($tickSize !== null && $stepSize !== null) {
                    if (! $this->sameSize($market->tick_size, $tickSize)) {
                        $updates['tick_size'] = $tickSize;
                    }

                    if (! $this->sameSize($market->step_size, $stepSize)) {
                        $updates['step_size'] = $stepSize;
                    }
                }

                $lastPrice = Arr::get($exchangeMarket, 'stats.lastPrice');

                if ($lastPrice !== null && ! $this->samePrice($market->last_price, $lastPrice)) {
                    $updates['last_price'] = number_format((float) $lastPrice, 12, '.', '');
                }

                if ($updates === []) {
                    $skipped++;

                    return;
                }

                $market->update($updates);
                $updated++;
            });

        return compact('updated', 'skipped', 'missing');
    }

    private function sameSize(mixed $stored, mixed $api): bool
    {
        return (string) (int) $stored === (string) (int) $api;
    }

    private function samePrice(mixed $stored, mixed $api): bool
    {
        return number_format((float) $stored, 12, '.', '') === number_format((float) $api, 12, '.', '');
    }
}
