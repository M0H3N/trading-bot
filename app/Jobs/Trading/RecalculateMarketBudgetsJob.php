<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\MarketBudgetService;
use App\Domain\Trading\Services\TradingQueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateMarketBudgetsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $uniqueFor = 30;

    public function __construct()
    {
        $this->onQueue(TradingQueueService::maintenance());
    }

    public function uniqueId(): string
    {
        return 'recalculate-market-budgets';
    }

    public function handle(MarketBudgetService $service): void
    {
        $service->redistributeBudgets();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RecalculateMarketBudgetsJob failed.', ['error' => $exception->getMessage()]);
    }
}
