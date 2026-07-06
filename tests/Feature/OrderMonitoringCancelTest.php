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

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
