<?php

namespace App\Console\Commands;

use App\Jobs\Trading\ExpireOpeningDealsJob;
use Illuminate\Console\Command;

class ExpireOpeningDeals extends Command
{
    protected $signature = 'trading:expire-opening-deals';

    protected $description = 'Dispatch job to expire abandoned opening deals and cancel active entry orders when market evaluation is disabled.';

    public function handle(): int
    {
        ExpireOpeningDealsJob::dispatch();

        $this->components->info('Expire opening deals job dispatched.');

        return self::SUCCESS;
    }
}
