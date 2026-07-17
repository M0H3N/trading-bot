<?php

namespace App\Console\Commands;

use App\Models\Deal;
use App\Models\HttpLog;
use App\Models\TradingOrder;
use Illuminate\Console\Command;

class PruneStaleData extends Command
{
    protected $signature = 'trading:prune-stale';

    protected $description = 'Delete HTTP logs older than 24 hours and expired unfilled deals (with their orders) older than 1 hour.';

    public function handle(): int
    {
        $httpLogsDeleted = HttpLog::query()
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $staleDealIds = Deal::query()
            ->where('status', 'expired')
            ->where('entry_amount', '<=', 0)
            ->where('created_at', '<', now()->subHour())
            ->pluck('id');

        $ordersDeleted = 0;
        $dealsDeleted = 0;

        if ($staleDealIds->isNotEmpty()) {
            $ordersDeleted = TradingOrder::query()
                ->whereIn('deal_id', $staleDealIds)
                ->delete();

            $dealsDeleted = Deal::query()
                ->whereIn('id', $staleDealIds)
                ->delete();
        }

        $this->components->info(sprintf(
            'Pruned %d HTTP log(s), %d order(s), and %d expired deal(s).',
            $httpLogsDeleted,
            $ordersDeleted,
            $dealsDeleted,
        ));

        return self::SUCCESS;
    }
}
