<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\TradingQueueService;
use App\Jobs\Trading\EvaluateMarketJob;
use App\Jobs\Trading\ManageExitJob;
use App\Jobs\Trading\MonitorOrderJob;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use App\Models\TradingSetting;
use App\Domain\Trading\Services\TradingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TradingQueueAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_job_uses_evaluate_queue(): void
    {
        $job = new EvaluateMarketJob(1);

        $this->assertSame(TradingQueueService::evaluate(), $job->queue);
    }

    public function test_monitor_job_uses_monitor_queue(): void
    {
        $job = new MonitorOrderJob(1);

        $this->assertSame(TradingQueueService::monitor(), $job->queue);
    }

    public function test_manage_exit_job_uses_exit_queue(): void
    {
        $job = new ManageExitJob(1);

        $this->assertSame(TradingQueueService::exit(), $job->queue);
    }

    public function test_worker_queues_lists_all_trading_queues_in_priority_order(): void
    {
        $this->assertSame([
            TradingQueueService::monitor(),
            TradingQueueService::exit(),
            TradingQueueService::evaluate(),
            TradingQueueService::maintenance(),
        ], TradingQueueService::workerQueues());
    }

    public function test_dispatch_exit_jobs_to_exit_queue(): void
    {
        Bus::fake();

        app(TradingSettingsService::class)->syncDefaults();
        TradingSetting::query()->where('key', 'exit_management_enabled')->update(['value' => '1']);

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'base_asset' => 'PEPE',
            'quote_asset' => 'TMN',
            'tick_size' => '4',
            'step_size' => '0',
            'is_active' => true,
        ]);

        Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'exiting',
            'entry_average_price' => '0.40',
            'entry_amount' => '1000',
            'opened_at' => now(),
        ]);

        $this->artisan('trading:dispatch', ['--scope' => 'exit'])->assertSuccessful();

        Bus::assertDispatched(ManageExitJob::class, function (ManageExitJob $job): bool {
            return $job->queue === TradingQueueService::exit();
        });
    }

    public function test_dispatch_monitor_jobs_use_monitor_queue(): void
    {
        Bus::fake();

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '1',
            'is_active' => true,
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'buy-test',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'open',
            'price' => '100',
            'amount' => '1',
        ]);

        $this->artisan('trading:dispatch', ['--scope' => 'monitor'])->assertSuccessful();

        Bus::assertDispatched(MonitorOrderJob::class, function (MonitorOrderJob $job): bool {
            return $job->queue === TradingQueueService::monitor();
        });
    }
}
