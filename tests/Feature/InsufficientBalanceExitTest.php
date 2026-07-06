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

class InsufficientBalanceExitTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_is_marked_insufficient_balance_when_exit_amount_exceeds_wallet(): void
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
            'entry_average_price' => '0.3940',
            'entry_amount' => '6267560',
            'opened_at' => now(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/account/balances' => Http::response([
                'result' => [
                    'balances' => [
                        'PEPE' => [
                            'asset' => 'PEPE',
                            'value' => 1000000,
                            'locked' => 0,
                        ],
                    ],
                ],
            ]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('insufficient_balance', $deal->status);
        $this->assertDatabaseMissing('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
        ]);
        $this->assertArrayHasKey('insufficient_balance', $deal->metadata ?? []);
    }

    public function test_exit_is_placed_when_wallet_covers_exit_amount(): void
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
        $this->assertSame('exiting', $deal->fresh()->status);
    }

    public function test_exit_is_skipped_when_wallet_balance_floors_to_zero(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'TRXTMN',
            'base_asset' => 'TRX',
            'quote_asset' => 'TMN',
            'tick_size' => '0',
            'step_size' => '1',
            'is_active' => true,
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '58000',
            'entry_amount' => '46.7',
            'opened_at' => now(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/account/balances' => Http::response([
                'result' => [
                    'balances' => [
                        'TRX' => [
                            'asset' => 'TRX',
                            'value' => 0.06397,
                            'locked' => 0,
                        ],
                    ],
                ],
            ]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/account/orders'));
        $this->assertDatabaseMissing('orders', [
            'deal_id' => $deal->id,
            'side' => 'sell',
        ]);
        $this->assertSame('entered', $deal->fresh()->status);
    }

    public function test_insufficient_balance_deal_is_not_managed_again(): void
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
            'status' => 'insufficient_balance',
            'entry_average_price' => '0.3940',
            'entry_amount' => '6267560',
            'opened_at' => now(),
        ]);

        Http::fake();

        app(ExitManagementService::class)->manage($deal);

        Http::assertNothingSent();
        $this->assertSame('insufficient_balance', $deal->fresh()->status);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
