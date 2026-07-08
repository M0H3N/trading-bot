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

class ConcurrentExitBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exit_repricing_keeps_open_order_when_wallet_cannot_cover_replacement(): void
    {
        config()->set('trading.exit_interval', 0);
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
            'status' => 'exiting',
            'entry_average_price' => '0.3940',
            'entry_amount' => '6267560',
            'opened_at' => now(),
        ]);

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'client_id' => 'Deal-1-tb-wallex-pepetmn-sell-existing',
            'mode' => 'live',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '0.3950',
            'amount' => '6267560',
            'metadata' => ['exit_percent' => 0.25],
            'created_at' => now()->subMinute(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['PEPETMN' => '0.3940', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '0.3930', 'quantity' => '1000000']],
                'ask' => [['price' => '0.3945', 'quantity' => '1000000']],
            ]]),
            'api.wallex.ir/v1/account/balances' => Http::response([
                'result' => [
                    'balances' => [
                        'PEPE' => [
                            'asset' => 'PEPE',
                            'value' => 1000,
                            'locked' => 0,
                        ],
                    ],
                ],
            ]),
            'api.wallex.ir/v1/account/orders/Deal-1-tb-wallex-pepetmn-sell-existing' => Http::response([
                'result' => [
                    'status' => 'NEW',
                    'executedQty' => '0',
                    'executedPrice' => '0.3950',
                ],
            ]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        Http::assertNotSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_contains($request->url(), '/account/orders/'));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/account/orders'));
        $this->assertDatabaseHas('orders', [
            'client_id' => 'Deal-1-tb-wallex-pepetmn-sell-existing',
            'status' => 'open',
        ]);
    }

    public function test_exit_place_order_422_is_handled_without_throwing(): void
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
            'entry_amount' => '291386',
            'opened_at' => now(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/account/balances' => Http::response([
                'result' => [
                    'balances' => [
                        'PEPE' => [
                            'asset' => 'PEPE',
                            'value' => 500000,
                            'locked' => 0,
                        ],
                    ],
                ],
            ]),
            'api.wallex.ir/v1/account/orders' => Http::response([
                'message' => 'اطلاعات وارد شده اشتباه‌است.',
                'success' => false,
                'code' => 422,
                'result' => [
                    'error_code' => [1006],
                    'quantity' => ['موجودی شما کافی نیست.'],
                ],
            ], 422),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertDatabaseMissing('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
        ]);
        $this->assertSame('entered', $deal->fresh()->status);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
