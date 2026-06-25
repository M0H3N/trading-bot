<?php

namespace App\Console\Commands;

use App\Domain\Trading\Services\TradingSettingsService;
use App\Jobs\Trading\ExpireOpeningDealsJob;
use Illuminate\Console\Command;

class ExpireOpeningDeals extends Command
{
    protected $signature = 'trading:expire-opening-deals';

    protected $description = 'Dispatch job to cancel opening deal entry orders and expire deals when market evaluation is disabled.';

    public function handle(TradingSettingsService $settings): int
    {
        if ($settings->marketEvaluationEnabled()) {
            return self::SUCCESS;
        }

        ExpireOpeningDealsJob::dispatch();

        $this->components->info('Expire opening deals job dispatched.');

        return self::SUCCESS;
    }
}
