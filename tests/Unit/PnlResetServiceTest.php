<?php

namespace Tests\Unit;

use App\Domain\Trading\Services\PnlResetService;
use App\Models\Deal;
use App\Models\Market;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PnlResetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_tmn_zeros_adjusted_pnl_values(): void
    {
        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '0.00000001',
            'last_price' => '1000000000',
            'is_active' => true,
        ]);

        Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'closed',
            'realized_pnl' => '500000',
            'realized_pnl_percent' => '5',
            'unexited_amount' => '0.001',
            'closed_at' => now(),
        ]);

        $service = app(PnlResetService::class);

        $this->assertSame(500000.0, $service->rawRealizedTmn());
        $this->assertSame(1000000.0, $service->rawUnrealizedTmn());

        $service->resetTmn();

        $this->assertSame(0.0, $service->adjustedRealizedTmn());
        $this->assertSame(0.0, $service->adjustedUnrealizedTmn());
    }
}
