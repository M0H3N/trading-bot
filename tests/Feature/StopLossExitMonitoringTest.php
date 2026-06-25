<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\ExitManagementService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StopLossExitMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_stop_loss_exit_at_top_ask_is_not_cancelled_and_replaced(): void
    {
        config()->set('trading.mode', 'paper');
        config()->set('trading.exit_interval', 0);
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'paper');

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
            'status' => 'stop_loss',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.01',
            'exit_average_price' => '1003000000',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'stop-loss-top-ask-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1003000000',
            'amount' => '0.01',
            'metadata' => ['exit_percent' => 0],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '990000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '990000000', 'quantity' => '1']],
                'ask' => [['price' => '1003000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $order->refresh();

        $this->assertSame('open', $order->status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('stop_loss', $deal->fresh()->status);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
