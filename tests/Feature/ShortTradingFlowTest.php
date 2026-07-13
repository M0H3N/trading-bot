<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\ExitManagementService;
use App\Domain\Trading\Services\MarketEvaluationService;
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

class ShortTradingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_market_evaluation_places_sell_entry_order(): void
    {
        config()->set('trading.mode', 'paper');
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');
        $this->setting('trading_mode', 'paper');

        $market = $this->shortMarket();

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response([
                'result' => [
                    'bid' => [['price' => '999000000', 'quantity' => '2']],
                    'ask' => [['price' => '1003000000', 'quantity' => '2']],
                ],
            ]),
        ]);

        $order = app(MarketEvaluationService::class)->evaluate($market);

        $this->assertNotNull($order);
        $this->assertSame('sell', $order->side);
        $this->assertDatabaseHas('deals', [
            'id' => $order->deal_id,
            'direction' => 'short',
            'status' => 'opening',
        ]);
    }

    public function test_short_market_evaluation_skips_when_short_disabled(): void
    {
        config()->set('trading.mode', 'paper');
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');
        $this->setting('trading_mode', 'paper');

        $market = $this->shortMarket(shortEnabled: false);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response([
                'result' => [
                    'bid' => [['price' => '999000000', 'quantity' => '2']],
                    'ask' => [['price' => '1003000000', 'quantity' => '2']],
                ],
            ]),
        ]);

        $order = app(MarketEvaluationService::class)->evaluate($market);

        $this->assertNull($order);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_short_entry_monitoring_cancels_sell_when_opportunity_is_gone(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');
        $this->setting('entry_threshold_percent', '0.10');

        $market = $this->shortMarket();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'short',
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'short-monitor-cancel',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'open',
            'price' => '1003000000',
            'amount' => '0.1',
        ]);

        Http::fake([
            'api.wallex.ir/v1/account/orders/short-monitor-cancel' => Http::response(['result' => ['status' => 'NEW', 'executedQty' => '0']]),
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response([
                'result' => [
                    'bid' => [['price' => '1000000000', 'quantity' => '2']],
                    'ask' => [['price' => '1000100000', 'quantity' => '2']],
                ],
            ]),
        ]);

        app(OrderMonitoringService::class)->monitor($order);

        $order->refresh();
        $deal->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('expired', $deal->status);
    }

    public function test_short_exit_management_places_buy_exit_order(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('initial_exit_percent', '0.10');

        $market = $this->shortMarket();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'short',
            'status' => 'entered',
            'entry_average_price' => '1003000000',
            'entry_amount' => '0.1',
            'opened_at' => now(),
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1003000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => [
                'bid' => [['price' => '1002000000', 'quantity' => '1']],
                'ask' => [['price' => '1003000000', 'quantity' => '1']],
            ]]),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'buy',
            'price' => '1001997000',
        ]);
    }

    public function test_short_exit_management_does_not_close_deal_when_entry_fee_is_in_quote_asset(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('exit_management_enabled', '1');
        $this->setting('initial_exit_percent', '0.10');

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'TRXTMN',
            'base_asset' => 'TRX',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '1',
            'last_price' => '59679',
            'is_active' => true,
            'long_enabled' => false,
            'short_enabled' => true,
        ]);

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'short',
            'status' => 'entered',
            'entry_average_price' => '59679',
            'entry_amount' => '5.2',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'TRXTMN',
            'client_id' => 'short-trx-entry-fee-test',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '59679',
            'amount' => '5.2',
            'quote_amount' => '310330.8',
        ]);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'order_id' => $order->id,
            'mode' => 'paper',
            'side' => 'sell',
            'price' => '59679',
            'amount' => '5.2',
            'quote_amount' => '310330.8',
            'fee' => '15.516540',
            'fee_asset' => 'TMN',
            'filled_at' => now(),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $deal->refresh();

        $this->assertSame('exiting', $deal->status);
        $this->assertNull($deal->closed_at);
        $this->assertDatabaseHas('orders', [
            'deal_id' => $deal->id,
            'side' => 'buy',
        ]);
    }

    public function test_entry_leg_scope_includes_filled_short_entries_without_trades(): void
    {
        $market = $this->shortMarket();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'short',
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'short-entry-leg-scope',
            'mode' => 'paper',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '1003000000',
            'amount' => '0.1',
        ]);

        $this->assertTrue(
            TradingOrder::query()->monitorable()->entryLeg()->whereKey($order->id)->exists(),
            'Filled short entry orders without a sell trade should be monitorable via entryLeg.',
        );
    }

    private function shortMarket(bool $shortEnabled = true): Market
    {
        return Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '1',
            'last_price' => '1000000000',
            'is_active' => true,
            'long_enabled' => false,
            'short_enabled' => $shortEnabled,
        ]);
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
