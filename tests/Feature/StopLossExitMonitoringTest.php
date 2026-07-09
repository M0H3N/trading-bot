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

    public function test_long_exit_places_sell_at_top_ask(): void
    {
        config()->set('trading.mode', 'paper');
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
            'status' => 'entered',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.01',
            'opened_at' => now(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '1002000000', 'quantity' => '1']],
                'ask' => [['price' => '1003000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'price' => '1003000000',
        ]);
    }

    public function test_stop_loss_deal_is_marked_stop_loss_closed_when_fully_exited(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('min_order_sum_tmn', '100000');

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '8',
            'is_active' => true,
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'stop_loss',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.00000100',
            'exit_average_price' => '1000000000',
            'exit_amount' => '0.00000100',
            'opened_at' => now(),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('stop_loss_closed', $deal->status);
        $this->assertNotNull($deal->closed_at);
        $this->assertTrue($deal->isClosed());
    }

    public function test_force_stop_loss_activates_immediately_when_fair_price_drops_eight_percent_below_entry(): void
    {
        config()->set('trading.mode', 'paper');
        config()->set('trading.exit_interval', 0);
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'paper');
        $this->setting('stop_loss_percent', '1.00');
        $this->setting('force_stop_loss_percent', '8.00');

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
            'status' => 'exiting',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.01',
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'force-stop-loss-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1001000000',
            'amount' => '0.01',
            'metadata' => ['exit_percent' => 0.10],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '919000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '918000000', 'quantity' => '1']],
                'ask' => [['price' => '919000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('stop_loss', $deal->status);
        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'status' => 'open',
            'price' => '919000000',
        ]);
    }

    public function test_stop_loss_does_not_activate_before_one_hour_breach(): void
    {
        config()->set('trading.mode', 'paper');
        config()->set('trading.exit_interval', 0);
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'paper');
        $this->setting('stop_loss_percent', '1.00');
        $this->setting('force_stop_loss_percent', '8.00');

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
            'status' => 'exiting',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.01',
            'metadata' => ['stop_loss_breach_at' => now()->subMinutes(30)->toIso8601String()],
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'stop-loss-wait-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1001000000',
            'amount' => '0.01',
            'metadata' => ['exit_percent' => 0.10],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '989000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '988000000', 'quantity' => '1']],
                'ask' => [['price' => '989000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertSame('exiting', $deal->fresh()->status);
    }

    public function test_stop_loss_activates_after_one_hour_breach(): void
    {
        config()->set('trading.mode', 'paper');
        config()->set('trading.exit_interval', 0);
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'paper');
        $this->setting('stop_loss_percent', '1.00');
        $this->setting('force_stop_loss_percent', '8.00');

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
            'status' => 'exiting',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.01',
            'metadata' => ['stop_loss_breach_at' => now()->subHours(2)->toIso8601String()],
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'stop-loss-hour-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1001000000',
            'amount' => '0.01',
            'metadata' => ['exit_percent' => 0.10],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '989000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '988000000', 'quantity' => '1']],
                'ask' => [['price' => '989000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertSame('stop_loss', $deal->fresh()->status);
    }

    public function test_stop_loss_breach_timestamp_is_recorded_on_first_threshold_cross(): void
    {
        config()->set('trading.mode', 'paper');
        config()->set('trading.exit_interval', 0);
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'paper');
        $this->setting('stop_loss_percent', '1.00');
        $this->setting('force_stop_loss_percent', '8.00');

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
            'status' => 'exiting',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.01',
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'stop-loss-breach-record-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1001000000',
            'amount' => '0.01',
            'metadata' => ['exit_percent' => 0.10],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '989000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '988000000', 'quantity' => '1']],
                'ask' => [['price' => '989000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('exiting', $deal->status);
        $this->assertArrayHasKey('stop_loss_breach_at', $deal->metadata ?? []);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
