<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\HttpLog;
use App\Models\Market;
use App\Models\TradingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneStaleDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_http_logs_older_than_twenty_four_hours(): void
    {
        $old = HttpLog::query()->create([
            'method' => 'GET',
            'url' => 'https://example.test/old',
            'status_code' => 200,
        ]);
        $old->forceFill(['created_at' => now()->subHours(25)])->save();

        $recent = HttpLog::query()->create([
            'method' => 'GET',
            'url' => 'https://example.test/recent',
            'status_code' => 200,
        ]);
        $recent->forceFill(['created_at' => now()->subHours(23)])->save();

        $this->artisan('trading:prune-stale')->assertSuccessful();

        $this->assertDatabaseMissing('http_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('http_logs', ['id' => $recent->id]);
    }

    public function test_command_deletes_expired_unfilled_deals_older_than_one_hour(): void
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

        $staleExpired = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'expired',
            'entry_amount' => 0,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHours(2),
        ]);
        $staleExpired->forceFill(['created_at' => now()->subHours(2)])->save();

        $staleOrder = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $staleExpired->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'prune-stale-order',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'cancelled',
            'price' => '1000000000',
            'amount' => '0.001',
            'filled_amount' => '0',
        ]);

        $recentExpired = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'expired',
            'entry_amount' => 0,
            'opened_at' => now()->subMinutes(30),
            'closed_at' => now()->subMinutes(30),
        ]);
        $recentExpired->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $recentOrder = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $recentExpired->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'prune-recent-order',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'cancelled',
            'price' => '1000000000',
            'amount' => '0.001',
            'filled_amount' => '0',
        ]);

        $expiredWithFill = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'expired',
            'entry_amount' => '0.001',
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHours(2),
        ]);
        $expiredWithFill->forceFill(['created_at' => now()->subHours(2)])->save();

        $opening = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'opening',
            'entry_amount' => 0,
            'opened_at' => now()->subHours(2),
        ]);
        $opening->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->artisan('trading:prune-stale')->assertSuccessful();

        $this->assertDatabaseMissing('deals', ['id' => $staleExpired->id]);
        $this->assertDatabaseMissing('orders', ['id' => $staleOrder->id]);
        $this->assertDatabaseHas('deals', ['id' => $recentExpired->id]);
        $this->assertDatabaseHas('orders', ['id' => $recentOrder->id]);
        $this->assertDatabaseHas('deals', ['id' => $expiredWithFill->id]);
        $this->assertDatabaseHas('deals', ['id' => $opening->id]);
    }
}
