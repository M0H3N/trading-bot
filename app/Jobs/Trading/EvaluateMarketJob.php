<?php

namespace App\Jobs\Trading;

use App\Domain\Trading\Services\MarketEvaluationService;
use App\Models\Market;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateMarketJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly int $marketId)
    {
        $this->onQueue((string) config('trading.queue'));
    }

    public function handle(MarketEvaluationService $service): void
    {
        $market = Market::query()->find($this->marketId);

        if (! $market) {
            return;
        }

        $service->evaluate($market);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('EvaluateMarketJob failed.', ['market_id' => $this->marketId, 'error' => $exception->getMessage()]);
    }
}
