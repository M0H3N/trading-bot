<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\OrderMonitoringService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\Trade;
use App\Models\TradingOrder;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderMonitoringCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_expires_opening_deal_when_entry_order_is_cancelled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');
        $this->setting('trading_mode', 'paper');

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
            'client_id' => 'cancel-entry-test',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'open',
            'price' => '999999999',
            'amount' => '0.001',
            'filled_amount' => '0',
            'quote_amount' => '1000000',
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => ['bid' => [['price' => '1000000000', 'quantity' => '1']], 'ask' => [['price' => '1000000000', 'quantity' => '1']]]]),
        ]);

        app(OrderMonitoringService::class)->monitor($order);

        $deal->refresh();
        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('expired', $deal->status);
        $this->assertNotNull($deal->closed_at);
    }

    public function test_monitor_marks_short_deal_entered_when_partial_entry_is_cancelled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');
        $this->setting('trading_mode', 'paper');

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
            'direction' => Deal::DIRECTION_SHORT,
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'cancel-partial-short-entry',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'partially_filled',
            'price' => '1003000000',
            'amount' => '0.02',
            'filled_amount' => '0.01',
            'quote_amount' => '20060000',
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '1000000000', 'quantity' => '2']],
                'ask' => [['price' => '1000100000', 'quantity' => '2']],
            ]]),
        ]);

        app(OrderMonitoringService::class)->monitor($order);

        $deal->refresh();
        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('entered', $deal->status);
        $this->assertGreaterThan(0, (float) $deal->entry_amount);
        $this->assertDatabaseHas('trades', ['order_id' => $order->id, 'side' => 'sell']);
    }

    public function test_monitor_recovers_opening_deal_with_cancelled_partial_entry(): void
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
            'direction' => Deal::DIRECTION_SHORT,
            'status' => 'opening',
            'entry_average_price' => '60134',
            'entry_amount' => '14.2',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'recover-cancelled-partial-short',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'cancelled',
            'price' => '60134',
            'amount' => '38',
            'filled_amount' => '14.2',
        ]);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'order_id' => $order->id,
            'exchange_trade_id' => '',
            'mode' => 'paper',
            'side' => 'sell',
            'price' => '60134',
            'amount' => '14.2',
            'quote_amount' => '853902.8',
            'fee' => '42.69514',
            'fee_asset' => 'TMN',
            'filled_at' => now(),
            'metadata' => ['source' => 'order_status'],
        ]);

        $this->assertTrue(
            TradingOrder::query()->monitorable()->entryLeg()->whereKey($order->id)->exists(),
        );

        app(OrderMonitoringService::class)->monitor($order);

        $this->assertSame('entered', $deal->refresh()->status);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
