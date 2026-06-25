<?php

namespace App\Console\Commands;

use App\Domain\Trading\Services\MarketSyncService;
use Illuminate\Console\Command;

class SyncMarkets extends Command
{
    protected $signature = 'markets:sync';

    protected $description = 'Sync market sizes and last prices from Wallex all-markets API.';

    public function handle(MarketSyncService $syncService): int
    {
        $result = $syncService->sync();

        $this->components->info(sprintf(
            'Markets synced. Updated: %d, unchanged: %d, missing in API: %d.',
            $result['updated'],
            $result['skipped'],
            $result['missing'],
        ));

        return self::SUCCESS;
    }
}
