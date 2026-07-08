<?php

namespace App\Console\Commands;

use App\Domain\Trading\Services\TradingQueueService;
use Illuminate\Console\Command;

class WorkTradingQueues extends Command
{
    protected $signature = 'trading:queue-work
        {--connection= : The name of the queue connection to work}
        {--name=trading : The name of the worker}
        {--sleep=3 : Number of seconds to sleep when no job is available}
        {--timeout=60 : The number of seconds a child process can run}
        {--tries=3 : Number of times to attempt a job before logging it failed}
        {--max-jobs=0 : Number of jobs to process before stopping}
        {--max-time=0 : The maximum number of seconds the worker should run}
        {--memory=128 : The memory limit in megabytes}';

    protected $description = 'Run a queue worker for all trading queues (no per-market names needed).';

    public function handle(): int
    {
        $connection = $this->option('connection') ?: (string) config('queue.default');
        $queues = implode(',', TradingQueueService::workerQueues());

        $this->components->info("Working trading queues [{$queues}] on connection [{$connection}].");

        return $this->call('queue:work', [
            'connection' => $connection,
            '--queue' => $queues,
            '--name' => $this->option('name'),
            '--sleep' => $this->option('sleep'),
            '--timeout' => $this->option('timeout'),
            '--tries' => $this->option('tries'),
            '--max-jobs' => $this->option('max-jobs'),
            '--max-time' => $this->option('max-time'),
            '--memory' => $this->option('memory'),
        ]);
    }
}
