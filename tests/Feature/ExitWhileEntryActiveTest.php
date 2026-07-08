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

class ExitWhileEntryActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_exit_management_skips_while_buy_order_is_still_active(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'base_asset' => 'PEPE',
            'quote_asset' => 'TMN',
            'tick_size' => '4',
            'step_size' => '0',
            'is_active' => true,
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'opening',
            'entry_average_price' => '0.3923',
            'entry_amount' => '291386',
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'client_id' => 'tb-wallex-pepetmn-buy-test',
            'mode' => 'live',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'partially_filled',
            'price' => '0.3923',
            'amount' => '6753411',
            'filled_amount' => '291386',
            'quote_amount' => '114280.6078',
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('opening', $deal->status);
        $this->assertNull($deal->closed_at);
        $this->assertDatabaseMissing('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
        ]);
    }

    public function test_exit_management_runs_after_buy_order_is_fully_filled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'base_asset' => 'PEPE',
            'quote_asset' => 'TMN',
            'tick_size' => '4',
            'step_size' => '0',
            'is_active' => true,
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '0.3923',
            'entry_amount' => '6753411',
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'client_id' => 'tb-wallex-pepetmn-buy-test',
            'mode' => 'live',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '0.3923',
            'amount' => '6753411',
            'filled_amount' => '6753411',
            'quote_amount' => '2649336.8000',
        ]);

        Http::fake([
            'api.wallex.ir/v1/account/balances' => Http::response([
                'result' => [
                    'balances' => [
                        'PEPE' => [
                            'asset' => 'PEPE',
                            'value' => 10000000,
                            'locked' => 0,
                        ],
                    ],
                ],
            ]),
            'api.wallex.ir/v1/account/orders' => Http::response([
                'result' => [
                    'id' => 'exit-order-1',
                    'status' => 'NEW',
                    'executedQty' => '0',
                    'executedPrice' => '0.3927',
                ],
            ]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
        ]);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
