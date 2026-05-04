<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\ExitManagementService;
use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManageExitJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly int $dealId)
    {
        $this->onQueue((string) config('trading.queue'));
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
