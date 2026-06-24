<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\ExitManagementService;
use App\Domain\Trading\Services\MarketEvaluationService;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TradingServiceTogglesTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_evaluation_can_be_disabled_while_exit_management_runs(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '0');
        $this->setting('exit_management_enabled', '1');

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '8',
            'is_active' => true,
        ]);

        Http::fake([
            'api.wallex.ir/v1/all-fairPrice' => Http::response(['result' => ['BTCTMN' => '1000000000', 'USDTTMN' => '70000']]),
            'api.wallex.ir/v1/depth*' => Http::response(['result' => ['bid' => [['price' => '1002000000', 'quantity' => '1']], 'ask' => [['price' => '1003000000', 'quantity' => '1']]]]),
        ]);

        $this->assertNull(app(MarketEvaluationService::class)->evaluate($market));

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'status' => 'entered',
            'entry_average_price' => '1000000000',
            'entry_amount' => '0.00000100',
            'exit_average_price' => '1000000000',
            'exit_amount' => '0.00000050',
            'opened_at' => now(),
        ]);

        app(ExitManagementService::class)->manage($deal);

        $this->assertSame('closed', $deal->refresh()->status);
    }

    public function test_market_evaluation_enabled_implies_exit_management_enabled(): void
    {
        app(TradingSettingsService::class)->syncDefaults();
        $this->setting('market_evaluation_enabled', '1');
        $this->setting('exit_management_enabled', '0');

        $settings = app(TradingSettingsService::class);

        $this->assertTrue($settings->marketEvaluationEnabled());
        $this->assertTrue($settings->exitManagementEnabled());
    }

    private function setting(string $key, string $value): void
    {
        TradingSetting::query()->where('key', $key)->update(['value' => $value]);
    }
}
