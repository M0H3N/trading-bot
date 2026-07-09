<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\ExitManagementService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImmediateExitFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_exit_records_trade_when_sell_order_fills_immediately(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('trading_mode', 'live');

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
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.01',
            'opened_at' => now(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '1050000000', 'quantity' => '1']],
                'ask' => [['price' => '1060000000', 'quantity' => '1']],
            ]]),
            'api.wallex.ir/v1/account/balances' => Http::response([
                'result' => [
                    'balances' => [
                        'BTC' => [
                            'asset' => 'BTC',
                            'value' => 1,
                            'locked' => 0,
                        ],
                    ],
                ],
            ]),
            'api.wallex.ir/v1/account/orders' => Http::response([
                'result' => [
                    'id' => 'exit-order-1',
                    'status' => 'FILLED',
                    'executedQty' => '0.01',
                    'executedPrice' => '1060000000',
                ],
            ]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'status' => 'filled',
            'price' => '1060000000',
            'filled_amount' => '0.01',
        ]);
        $this->assertDatabaseHas('trades', [
            'deal_id' => $deal->id,
            'side' => 'sell',
            'amount' => '0.010000000000',
            'price' => '1060000000',
        ]);
        $this->assertSame('closed', $deal->status);
        $this->assertNotNull($deal->closed_at);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
