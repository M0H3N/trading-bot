<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\CancelOpenOrderService;
use App\Jobs\Trading\CancelAllOpenOrdersJob;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CancelOpenOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_cancels_active_buy_order(): void
    {
        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '1002000000', 'quantity' => '1']],
                'ask' => [['price' => '1003000000', 'quantity' => '1']],
            ]]),
        ]);

        $order = $this->openOrder('buy');

        app(CancelOpenOrderService::class)->cancel($order);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'side' => 'buy',
            'status' => 'cancelled',
        ]);
    }

    public function test_service_cancels_active_sell_order(): void
    {
        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '1002000000', 'quantity' => '1']],
                'ask' => [['price' => '1003000000', 'quantity' => '1']],
            ]]),
        ]);

        $order = $this->openOrder('sell');

        app(CancelOpenOrderService::class)->cancel($order);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'side' => 'sell',
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_all_job_dispatches_to_queue(): void
    {
        Bus::fake();

        CancelAllOpenOrdersJob::dispatch();

        Bus::assertDispatched(CancelAllOpenOrdersJob::class);
    }

    private function openOrder(string $side): TradingOrder
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
            'status' => $side === 'buy' ? 'opening' : 'exiting',
            'opened_at' => now(),
        ]);

        return TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'cancel-open-'.$side.'-test',
            'mode' => 'paper',
            'side' => $side,
            'type' => 'limit',
            'status' => 'open',
            'price' => '1000000000',
            'amount' => '0.01',
            'filled_amount' => '0',
            'quote_amount' => '10000000',
        ]);
    }
}
