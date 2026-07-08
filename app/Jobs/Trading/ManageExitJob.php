<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\ExitManagementService;
use App\Domain\Trading\Services\TradingQueueService;
use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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
        $this->onQueue(TradingQueueService::exit());
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlapKey()))
                ->releaseAfter(10)
                ->expireAfter((int) config('trading.lock_ttl') + 30),
        ];
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

    protected function overlapKey(): string
    {
        $deal = Deal::query()->with('market')->find($this->dealId);

        if (! $deal?->market) {
            return "trading:exit:deal:{$this->dealId}";
        }

        return TradingQueueService::exitOverlapKey($deal->market->exchange, $deal->market->base_asset);
    }
}
