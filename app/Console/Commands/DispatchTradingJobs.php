<?php

namespace App\Console\Commands;

use App\Domain\Trading\Services\TradingSettingsService;
use App\Jobs\Trading\EvaluateMarketJob;
use App\Jobs\Trading\ManageExitJob;
use App\Jobs\Trading\MonitorOrderJob;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use Illuminate\Console\Command;

class DispatchTradingJobs extends Command
{
    protected $signature = 'trading:dispatch
        {--scope=all : Scope to dispatch: all, evaluate, monitor, or exit}
        {--force : Dispatch even when the bot is disabled}';

    protected $description = 'Dispatch trading evaluation, order monitoring, and exit management jobs.';

    public function handle(TradingSettingsService $settings): int
    {
        if (! $this->option('force') && ! $settings->botEnabled()) {
            $this->components->info('Trading bot is disabled.');

            return self::SUCCESS;
        }

        $scope = (string) $this->option('scope');

        if (in_array($scope, ['all', 'evaluate'], true)) {
            Market::query()->active()->pluck('id')->each(fn (int $id) => EvaluateMarketJob::dispatch($id));
        }

        if (in_array($scope, ['all', 'monitor'], true)) {
            TradingOrder::query()->active()->pluck('id')->each(fn (int $id) => MonitorOrderJob::dispatch($id));
        }

        if (in_array($scope, ['all', 'exit'], true)) {
            Deal::query()->open()->pluck('id')->each(fn (int $id) => ManageExitJob::dispatch($id));
        }

        $this->components->info('Trading jobs dispatched.');

        return self::SUCCESS;
    }
}
