<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\CancelDealExitOrdersService;
use App\Jobs\Trading\CancelDealExitOrdersJob;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CancelDealExitOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_cancels_active_exit_orders_for_manually_closed_deal(): void
    {
        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => ['bid' => [['price' => '1002000000', 'quantity' => '1']], 'ask' => [['price' => '1003000000', 'quantity' => '1']]]]),
        ]);

        $deal = $this->dealWithExitOrder(status: 'open', dealStatus: 'manually_closed');

        app(CancelDealExitOrdersService::class)->cancelForDeal($deal->id);

        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'status' => 'cancelled',
        ]);
    }

    public function test_service_skips_deals_that_are_not_manually_closed(): void
    {
        $deal = $this->dealWithExitOrder(status: 'open', dealStatus: 'exiting');

        app(CancelDealExitOrdersService::class)->cancelForDeal($deal->id);

        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'status' => 'open',
        ]);
    }

    public function test_job_is_dispatched_when_deal_is_manually_closed(): void
    {
        Bus::fake();

        $deal = Deal::query()->create([
            'market_id' => Market::query()->create([
                'exchange' => 'wallex',
                'symbol' => 'BTCTMN',
                'base_asset' => 'BTC',
                'quote_asset' => 'TMN',
                'tick_size' => '1',
                'step_size' => '1',
                'is_active' => true,
            ])->id,
            'mode' => 'paper',
            'status' => 'exiting',
            'opened_at' => now(),
        ]);

        CancelDealExitOrdersJob::dispatch($deal->id);

        Bus::assertDispatched(CancelDealExitOrdersJob::class, fn (CancelDealExitOrdersJob $job): bool => $job->dealId === $deal->id);
    }

    private function dealWithExitOrder(string $status, string $dealStatus): Deal
    {
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
            'status' => $dealStatus,
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.001',
            'opened_at' => now(),
            'closed_at' => $dealStatus === 'manually_closed' ? now() : null,
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'test-sell-'.$deal->id,
            'external_id' => 'paper-test-sell-'.$deal->id,
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => $status,
            'price' => '1010000000',
            'amount' => '0.001',
            'filled_amount' => '0',
            'quote_amount' => '1010000',
        ]);

        return $deal;
    }
}
