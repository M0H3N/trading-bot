<?php

namespace Tests\Feature;

use App\Domain\Exchange\ExchangeManager;
use App\Domain\Trading\Services\MarketEvaluationService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Infrastructure\Exchange\Paper\PaperExchangeClient;
use App\Models\Market;
use App\Models\TradingOrder;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaperTradingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_evaluation_places_only_one_active_paper_entry_order(): void
    {
        config()->set('trading.enabled', true);
        config()->set('trading.mode', 'paper');
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('bot_enabled', '1');
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

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => ['bid' => [['price' => '1002000000', 'quantity' => '1']], 'ask' => [['price' => '1003000000', 'quantity' => '1']]]]),
        ]);

        app(MarketEvaluationService::class)->evaluate($market);
        app(MarketEvaluationService::class)->evaluate($market);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['symbol' => 'BTCTMN', 'mode' => 'paper', 'side' => 'buy', 'status' => 'open']);
    }

    public function test_paper_order_status_fills_against_top_of_book(): void
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

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'paper-fill-test',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'open',
            'price' => '100',
            'amount' => '2',
        ]);

        Http::fake([
            'api.wallex.ir/v1/depth*' => Http::response(['result' => ['bid' => [['price' => '99', 'quantity' => '1']], 'ask' => [['price' => '100', 'quantity' => '1']]]]),
        ]);

        $client = app(ExchangeManager::class)->client('wallex', 'paper');

        $this->assertInstanceOf(PaperExchangeClient::class, $client);
        $this->assertTrue($client->getOrderStatus($order->client_id)->isFilled());
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
