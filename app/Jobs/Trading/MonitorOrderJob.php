<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\OrderMonitoringService;
use App\Models\TradingOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MonitorOrderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue((string) config('trading.queue'));
    }

    public function handle(OrderMonitoringService $service): void
    {
        $order = TradingOrder::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $service->monitor($order);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('MonitorOrderJob failed.', ['order_id' => $this->orderId, 'error' => $exception->getMessage()]);
    }
}
