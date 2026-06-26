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

class ExitWalletGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_exit_is_blocked_when_open_deals_overbook_wallet(): void
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

        Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '0.3938',
            'entry_amount' => '5487108',
            'opened_at' => now(),
        ]);

        Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '0.3937',
            'entry_amount' => '6208220',
            'opened_at' => now()->subMinute(),
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '0.3940',
            'entry_amount' => '6267560',
            'opened_at' => now()->subMinutes(2),
        ]);

        Http::fake([
            'api.wallex.ir/v1/account/balances' => Http::response([
                'result' => [
                    'balances' => [
                        'PEPE' => [
                            'asset' => 'PEPE',
                            'value' => 15407942,
                            'locked' => 0,
                        ],
                    ],
                ],
            ]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertDatabaseMissing('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
        ]);
    }

    public function test_exit_is_placed_when_wallet_covers_open_deal_remaining(): void
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
