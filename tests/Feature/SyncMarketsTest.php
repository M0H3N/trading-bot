<?php

namespace Tests\Feature;

use App\Models\Market;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMarketsTest extends TestCase
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
                                'stats' => [
                                    'lastPrice' => '165829.0000000000000000',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('markets:sync')->assertSuccessful();

        $market->refresh();

        $this->assertSame('0.000000000000', $market->tick_size);
        $this->assertSame('2.000000000000', $market->step_size);
        $this->assertSame('165829.000000000000', $market->last_price);
    }

    public function test_sync_skips_markets_when_values_already_match(): void
    {
        Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '0',
            'step_size' => '8',
            'last_price' => '5000000000.000000000000',
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
                                'stats' => [
                                    'lastPrice' => '5000000000.0000000000000000',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('markets:sync')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();
    }

    public function test_sync_updates_last_price_when_only_price_differs(): void
    {
        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'ETHTMN',
            'base_asset' => 'ETH',
            'quote_asset' => 'TMN',
            'tick_size' => '0',
            'step_size' => '4',
            'last_price' => '0',
            'is_active' => true,
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-markets' => Http::response([
                'result' => [
                    'symbols' => [
                        'ETHTMN' => [
                            'EXCHANGE' => [
                                'symbol' => 'ETHTMN',
                                'tickSize' => 0,
                                'stepSize' => 4,
                                'stats' => [
                                    'lastPrice' => '250000000.0000000000000000',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('markets:sync')
            ->expectsOutputToContain('Updated: 1')
            ->assertSuccessful();

        $market->refresh();

        $this->assertSame('250000000.000000000000', $market->last_price);
    }
}
