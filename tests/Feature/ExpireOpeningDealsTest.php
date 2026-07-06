<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\ExpireOpeningDealsService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Jobs\Trading\ExpireOpeningDealsJob;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpireOpeningDealsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_service_does_not_cancel_active_orders_when_market_evaluation_is_enabled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');

        $deal = $this->openingDealWithBuyOrder();

        app(ExpireOpeningDealsService::class)->expire();

        $deal->refresh();
        $this->assertSame('opening', $deal->status);
        $this->assertDatabaseHas('orders', ['deal_id' => $deal->id, 'status' => 'open']);
    }

    public function test_expire_service_expires_abandoned_opening_deals_when_market_evaluation_is_enabled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');

        $deal = $this->openingDealWithBuyOrder(status: 'cancelled');

        app(ExpireOpeningDealsService::class)->expire();

        $deal->refresh();
        $this->assertSame('expired', $deal->status);
        $this->assertNotNull($deal->closed_at);
    }

    public function test_expire_service_cancels_active_buy_orders_and_expires_opening_deals(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '0');
        $this->setting('trading_mode', 'paper');

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => ['bid' => [['price' => '1002000000', 'quantity' => '1']], 'ask' => [['price' => '1003000000', 'quantity' => '1']]]]),
        ]);

        $deal = $this->openingDealWithBuyOrder();

        app(ExpireOpeningDealsService::class)->expire();

        $deal->refresh();
        $this->assertSame('expired', $deal->status);
        $this->assertNotNull($deal->closed_at);
        $this->assertDatabaseHas('orders', ['deal_id' => $deal->id, 'side' => 'buy', 'status' => 'cancelled']);
    }

    public function test_opening_deal_stays_opening_while_filled_entry_order_awaits_trade_recording(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '0');

        $deal = $this->openingDealWithBuyOrder(status: 'filled');

        app(ExpireOpeningDealsService::class)->expire();

        $this->assertSame('opening', $deal->refresh()->status);
        $this->assertDatabaseHas('orders', ['deal_id' => $deal->id, 'status' => 'filled']);
    }

    public function test_command_dispatches_job(): void
    {
        Bus::fake();

        app(TradingSettingsService::class)->syncDefaults();

        $this->artisan('trading:expire-opening-deals')->assertSuccessful();

        Bus::assertDispatched(ExpireOpeningDealsJob::class);
    }

    private function openingDealWithBuyOrder(string $status = 'open'): Deal
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

        TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'test-buy-'.$deal->id,
            'external_id' => 'paper-test-buy-'.$deal->id,
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => $status,
            'price' => '1000000000',
            'amount' => '0.001',
            'filled_amount' => '0',
            'quote_amount' => '1000000',
        ]);

        return $deal;
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
