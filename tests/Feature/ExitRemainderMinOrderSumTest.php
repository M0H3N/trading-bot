<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\ExitManagementService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExitRemainderMinOrderSumTest extends TestCase
{
    use RefreshDatabase;

    public function test_exit_closes_tmn_deal_when_remainder_notional_is_below_min_order_sum(): void
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
            'status' => 'entered',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.00000100',
            'exit_average_price' => '1000000000',
            'exit_amount' => '0.00000050',
            'opened_at' => now(),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('closed', $deal->status);
        $this->assertNotNull($deal->closed_at);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_exit_closes_usdt_deal_when_remainder_notional_is_below_min_order_sum(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('min_order_sum_usdt', '10');

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCUSDT',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'tick_size' => '2',
            'step_size' => '8',
            'is_active' => true,
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'exiting',
            'entry_average_price' => '50000',
            'entry_amount' => '0.00100000',
            'exit_average_price' => '50000',
            'exit_amount' => '0.00099900',
            'opened_at' => now(),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('closed', $deal->status);
        $this->assertNotNull($deal->closed_at);
        $this->assertDatabaseCount('orders', 0);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
