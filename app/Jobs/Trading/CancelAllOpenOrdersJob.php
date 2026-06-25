<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\CancelOpenOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelAllOpenOrdersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct()
    {
        $this->onQueue((string) config('trading.queue'));
    }

    public function handle(CancelOpenOrderService $service): void
    {
        Cache::lock('trading:cancel-all-open-orders', (int) config('trading.lock_ttl'))->block(60, function () use ($service): void {
            $service->cancelAllActive();
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CancelAllOpenOrdersJob failed.', ['error' => $exception->getMessage()]);
    }
}
