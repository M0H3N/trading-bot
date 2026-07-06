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

class ExitTopAskFloorTest extends TestCase
{
    use RefreshDatabase;

    public function test_exit_repricing_floors_at_exit_top_ask_from_percent_instead_of_top_ask(): void
    {
        config()->set('trading.mode', 'paper');
        config()->set('trading.exit_interval', 0);
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'paper');
        $this->setting('exit_top_ask_from_percent', '0.07');
        $this->setting('exit_step_percent', '0.01');

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
            'client_id' => 'exit-floor-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1000800000',
            'amount' => '0.01',
            'metadata' => ['exit_percent' => 0.08],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '980000000', 'quantity' => '1']],
                'ask' => [['price' => '990000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'status' => 'open',
            'price' => '1000700000.0',
        ]);
        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'status' => 'cancelled',
            'client_id' => 'exit-floor-test',
        ]);

        $replacement = TradingOrder::query()
            ->where('deal_id', $deal->id)
            ->where('side', 'sell')
            ->where('status', 'open')
            ->firstOrFail();

        $this->assertSame(0.07, (float) ($replacement->metadata['exit_percent'] ?? 0));
    }

    public function test_exit_percent_does_not_drop_below_exit_top_ask_from_percent(): void
    {
        config()->set('trading.mode', 'paper');
        config()->set('trading.exit_interval', 0);
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'paper');
        $this->setting('exit_top_ask_from_percent', '0.07');
        $this->setting('exit_step_percent', '0.01');

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
            'client_id' => 'exit-floor-hold-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1000700000',
            'amount' => '0.01',
            'metadata' => ['exit_percent' => 0.07],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '980000000', 'quantity' => '1']],
                'ask' => [['price' => '990000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $replacement = TradingOrder::query()
            ->where('deal_id', $deal->id)
            ->where('side', 'sell')
            ->where('status', 'open')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(0.07, (float) ($replacement->metadata['exit_percent'] ?? 0));
        $this->assertSame('1000700000', (string) $replacement->price);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
