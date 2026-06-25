<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\CancelDealExitOrdersService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelDealExitOrdersJob implements ShouldQueue
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

    public function handle(CancelDealExitOrdersService $service): void
    {
        Cache::lock("trading:cancel-deal-exit-orders:{$this->dealId}", (int) config('trading.lock_ttl'))->block(5, function () use ($service): void {
            $service->cancelForDeal($this->dealId);
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CancelDealExitOrdersJob failed.', [
            'deal_id' => $this->dealId,
            'error' => $exception->getMessage(),
        ]);
    }
}
