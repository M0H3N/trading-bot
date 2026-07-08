<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Models\Market;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealTest extends TestCase
{
    use RefreshDatabase;

    public function test_manually_closed_is_treated_as_closed(): void
    {
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
            'status' => 'manually_closed',
            'closed_at' => now(),
        ]);

        $this->assertTrue($deal->isClosed());
        $this->assertFalse(Deal::query()->open()->whereKey($deal->id)->exists());
        $this->assertTrue(Deal::query()->close()->whereKey($deal->id)->exists());
    }

    public function test_stop_loss_closed_is_treated_as_closed(): void
    {
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
            'status' => 'stop_loss_closed',
            'closed_at' => now(),
        ]);

        $this->assertTrue($deal->isClosed());
        $this->assertFalse(Deal::query()->open()->whereKey($deal->id)->exists());
        $this->assertTrue(Deal::query()->close()->whereKey($deal->id)->exists());
    }
}
