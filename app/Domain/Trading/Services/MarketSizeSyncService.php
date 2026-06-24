<?php

namespace App\Domain\Trading\Services;

use App\Infrastructure\Exchange\Wallex\WallexClient;
use App\Models\Market;
use Illuminate\Support\Arr;

class MarketSizeSyncService
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

                $tickSize = Arr::get($exchangeMarket, 'tickSize');
                $stepSize = Arr::get($exchangeMarket, 'stepSize');

                if ($tickSize === null || $stepSize === null) {
                    $missing++;

                    return;
                }

                if ($this->sameSize($market->tick_size, $tickSize) && $this->sameSize($market->step_size, $stepSize)) {
                    $skipped++;

                    return;
                }

                $market->update([
                    'tick_size' => $tickSize,
                    'step_size' => $stepSize,
                ]);

                $updated++;
            });

        return compact('updated', 'skipped', 'missing');
    }

    private function sameSize(mixed $stored, mixed $api): bool
    {
        return (string) (int) $stored === (string) (int) $api;
    }
}
