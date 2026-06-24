<?php

namespace App\Console\Commands;

use App\Domain\Trading\Services\MarketSizeSyncService;
use Illuminate\Console\Command;

class SyncMarketSizes extends Command
{
    protected $signature = 'markets:sync-sizes';

    protected $description = 'Sync market tick_size and step_size from Wallex all-markets API.';

    public function handle(MarketSizeSyncService $syncService): int
    {
        $result = $syncService->sync();

        $this->components->info(sprintf(
            'Market sizes synced. Updated: %d, unchanged: %d, missing in API: %d.',
            $result['updated'],
            $result['skipped'],
            $result['missing'],
        ));

        return self::SUCCESS;
    }
}
