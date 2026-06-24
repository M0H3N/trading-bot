<?php

namespace Tests\Feature;

use App\Models\Market;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMarketSizesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_updates_market_sizes_when_api_values_differ(): void
    {
        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'USDTTMN',
            'base_asset' => 'USDT',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '1',
            'is_active' => true,
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-markets' => Http::response([
                'result' => [
                    'symbols' => [
                        'USDTTMN' => [
                            'EXCHANGE' => [
                                'symbol' => 'USDTTMN',
                                'tickSize' => 0,
                                'stepSize' => 2,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('markets:sync-sizes')->assertSuccessful();

        $market->refresh();

        $this->assertSame('0.000000000000', $market->tick_size);
        $this->assertSame('2.000000000000', $market->step_size);
    }

    public function test_sync_skips_markets_when_sizes_already_match(): void
    {
        Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '0',
            'step_size' => '8',
            'is_active' => true,
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-markets' => Http::response([
                'result' => [
                    'symbols' => [
                        'BTCTMN' => [
                            'EXCHANGE' => [
                                'symbol' => 'BTCTMN',
                                'tickSize' => 0,
                                'stepSize' => 8,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('markets:sync-sizes')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();
    }
}
