<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\OrderMonitoringService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImmediateEntryFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_records_deal_when_entry_order_is_already_filled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');

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
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'immediate-fill-test',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '1000000000',
            'amount' => '0.01',
            'filled_amount' => '0.01',
        ]);

        Http::fake([
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '999000000', 'quantity' => '1']],
                'ask' => [['price' => '1000000000', 'quantity' => '1']],
            ]]),
        ]);

        app(OrderMonitoringService::class)->monitor($order);

        $deal->refresh();

        $this->assertDatabaseHas('trades', [
            'order_id' => $order->id,
            'side' => 'buy',
            'amount' => '0.010000000000',
        ]);
        $this->assertSame('entered', $deal->status);
        $this->assertGreaterThan(0, (float) $deal->entry_amount);
        $this->assertGreaterThan(0, (float) $deal->entry_average_price);
    }

    public function test_monitor_transitions_deal_to_entered_when_active_entry_order_becomes_filled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');

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
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'active-to-filled-test',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1000000000',
            'amount' => '0.01',
            'filled_amount' => '0',
        ]);

        Http::fake([
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '999000000', 'quantity' => '1']],
                'ask' => [['price' => '1000000000', 'quantity' => '1']],
            ]]),
        ]);

        app(OrderMonitoringService::class)->monitor($order);

        $deal->refresh();
        $order->refresh();

        $this->assertSame('filled', $order->status);
        $this->assertSame('entered', $deal->status);
        $this->assertDatabaseHas('trades', ['order_id' => $order->id, 'side' => 'buy']);
    }

    public function test_monitor_recovers_deal_stuck_in_opening_after_entry_fill(): void
    {
        app(TradingSettingsService::class)->syncDefaults();

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
            'status' => 'opening',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.010000000000',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'stuck-opening-recovery',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '1000000000',
            'amount' => '0.01',
            'filled_amount' => '0.01',
        ]);

        \App\Models\Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'order_id' => $order->id,
            'exchange_trade_id' => '',
            'mode' => 'paper',
            'side' => 'buy',
            'price' => '1000000000',
            'amount' => '0.01',
            'quote_amount' => '10000000',
            'fee' => '0',
            'fee_asset' => 'BTC',
            'filled_at' => now(),
            'metadata' => ['source' => 'order_status'],
        ]);

        app(OrderMonitoringService::class)->monitor($order);

        $this->assertSame('entered', $deal->refresh()->status);
    }

    public function test_monitorable_scope_includes_filled_entries_without_trades(): void
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
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'monitorable-scope-test',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '1',
            'amount' => '1',
        ]);

        $this->assertTrue(
            TradingOrder::query()->monitorable()->entryLeg()->whereKey($order->id)->exists(),
            'Filled entry-leg orders without a matching trade should still be monitorable.',
        );
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
