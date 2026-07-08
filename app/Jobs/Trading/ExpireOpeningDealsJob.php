<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\ExpireOpeningDealsService;
use App\Domain\Trading\Services\TradingQueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireOpeningDealsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct()
    {
        $this->onQueue(TradingQueueService::maintenance());
    }

    public function handle(ExpireOpeningDealsService $service): void
    {
        Cache::lock('trading:expire-opening-deals', (int) config('trading.lock_ttl'))->block(5, function () use ($service): void {
            $service->expire();
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ExpireOpeningDealsJob failed.', ['error' => $exception->getMessage()]);
    }
}
