<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\ExitManagementService;
use App\Domain\Trading\Services\TradingQueueService;
use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManageExitJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $uniqueFor = 90;

    public function __construct(public readonly int $dealId)
    {
        $this->onQueue(TradingQueueService::exit());
    }

    public function uniqueId(): string
    {
        return 'manage-exit:'.$this->dealId;
    }

    public function handle(ExitManagementService $service): void
    {
        $deal = Deal::query()->find($this->dealId);

        if (! $deal) {
            return;
        }

        $service->manage($deal);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ManageExitJob failed.', ['deal_id' => $this->dealId, 'error' => $exception->getMessage()]);
    }
}
