<?php

namespace Tests\Unit;

use App\Domain\Trading\Services\MarketBudgetService;
use App\Domain\Trading\Services\TradeRecorder;
use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\MarketBudget;
use App\Models\TradingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_budget_is_split_across_active_markets_with_same_quote_asset(): void
    {
        Queue::fake();
        config()->set('trading.paper.default_quote_balance', '100000000');
        app(TradingSettingsService::class)->syncDefaults();

        $btcMarket = $this->longMarket('BTCTMN', 'BTC');
        $ethMarket = $this->longMarket('ETHTMN', 'ETH');

        app(MarketBudgetService::class)->loadBudgetsFromExchange();

        $btcBudget = MarketBudget::query()->where('market_id', $btcMarket->id)->where('deal_type', 'long')->firstOrFail();
        $ethBudget = MarketBudget::query()->where('market_id', $ethMarket->id)->where('deal_type', 'long')->firstOrFail();

        $this->assertSame('TMN', $btcBudget->budget_asset);
        $this->assertEqualsWithDelta(50_000_000.0, (float) $btcBudget->budget, 0.01);
        $this->assertEqualsWithDelta(50_000_000.0, (float) $ethBudget->budget, 0.01);
    }

    public function test_apply_deal_budget_delta_increments_used_budget_atomically_for_long_deal(): void
    {
        Queue::fake();
        config()->set('trading.paper.default_quote_balance', '100000000');
        app(TradingSettingsService::class)->syncDefaults();

        $market = $this->longMarket('BTCTMN', 'BTC');
        app(MarketBudgetService::class)->loadBudgetsFromExchange();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'long',
            'status' => 'entered',
            'entry_quote' => '30000000',
            'exit_quote' => '10000000',
            'opened_at' => now(),
        ]);

        $service = app(MarketBudgetService::class);
        $service->applyDealBudgetDelta($deal, 0.0);

        $budget = MarketBudget::query()->where('market_id', $market->id)->where('deal_type', 'long')->firstOrFail();
        $this->assertEqualsWithDelta(20_000_000.0, (float) $budget->used_budget, 0.01);
        $this->assertEqualsWithDelta(80_000_000.0, $service->availableForEntry($market, Deal::DIRECTION_LONG), 0.01);
    }

    public function test_apply_deal_budget_delta_decrements_when_exit_quote_increases(): void
    {
        Queue::fake();
        app(TradingSettingsService::class)->syncDefaults();

        $market = $this->longMarket('BTCTMN', 'BTC');
        app(MarketBudgetService::class)->loadBudgetsFromExchange();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'long',
            'status' => 'entered',
            'entry_quote' => '30000000',
            'exit_quote' => '0',
            'opened_at' => now(),
        ]);

        $service = app(MarketBudgetService::class);
        $service->applyDealBudgetDelta($deal, 0.0);

        $previousExposure = $service->dealExposure($deal);
        $deal->forceFill(['exit_quote' => '10000000'])->save();
        $service->applyDealBudgetDelta($deal, $previousExposure);

        $budget = MarketBudget::query()->where('market_id', $market->id)->where('deal_type', 'long')->firstOrFail();
        $this->assertEqualsWithDelta(20_000_000.0, (float) $budget->used_budget, 0.01);
    }

    public function test_apply_deal_budget_delta_releases_budget_when_deal_closes(): void
    {
        Queue::fake();
        app(TradingSettingsService::class)->syncDefaults();

        $market = $this->longMarket('BTCTMN', 'BTC');
        app(MarketBudgetService::class)->loadBudgetsFromExchange();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'long',
            'status' => 'entered',
            'entry_quote' => '30000000',
            'exit_quote' => '0',
            'opened_at' => now(),
        ]);

        $service = app(MarketBudgetService::class);
        $service->applyDealBudgetDelta($deal, 0.0);

        $previousExposure = $service->dealExposure($deal);
        $deal->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
        $service->applyDealBudgetDelta($deal, $previousExposure);

        $budget = MarketBudget::query()->where('market_id', $market->id)->where('deal_type', 'long')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $budget->used_budget, 0.01);
    }

    public function test_short_used_budget_tracks_open_deal_base_exposure(): void
    {
        Queue::fake();
        config()->set('trading.paper.default_base_balance', '1');
        app(TradingSettingsService::class)->syncDefaults();

        $market = Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '1',
            'last_price' => '1000000000',
            'is_active' => true,
            'long_enabled' => false,
            'short_enabled' => true,
        ]);

        app(MarketBudgetService::class)->loadBudgetsFromExchange();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'short',
            'status' => 'entered',
            'entry_amount' => '0.4',
            'exit_amount' => '0.1',
            'opened_at' => now(),
        ]);

        $service = app(MarketBudgetService::class);
        $service->applyDealBudgetDelta($deal, 0.0);

        $budget = MarketBudget::query()->where('market_id', $market->id)->where('deal_type', 'short')->firstOrFail();
        $this->assertEqualsWithDelta(0.3, (float) $budget->used_budget, 0.000001);
    }

    public function test_trade_recorder_updates_used_budget_incrementally(): void
    {
        Queue::fake();
        app(TradingSettingsService::class)->syncDefaults();

        $market = $this->longMarket('BTCTMN', 'BTC');
        app(MarketBudgetService::class)->loadBudgetsFromExchange();

        $deal = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'paper',
            'direction' => 'long',
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $order = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'client_id' => 'budget-trade-recorder',
            'mode' => 'paper',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '100',
            'amount' => '200000',
        ]);

        app(TradeRecorder::class)->recordFilledOrder($order, '100', '200000');

        $budget = MarketBudget::query()->where('market_id', $market->id)->where('deal_type', 'long')->firstOrFail();
        $this->assertEqualsWithDelta(20_000_000.0, (float) $budget->used_budget, 0.01);
        $this->assertEqualsWithDelta(20_000_000.0, (float) $deal->fresh()->entry_quote, 0.01);
    }

    public function test_reset_zeros_used_budget_and_reloads_allocations(): void
    {
        Queue::fake();
        config()->set('trading.paper.default_quote_balance', '90000000');
        app(TradingSettingsService::class)->syncDefaults();

        $market = $this->longMarket('BTCTMN', 'BTC');
        app(MarketBudgetService::class)->loadBudgetsFromExchange();

        MarketBudget::query()->where('market_id', $market->id)->where('deal_type', 'long')->update(['used_budget' => '1000']);

        app(MarketBudgetService::class)->reset();

        $budget = MarketBudget::query()->where('market_id', $market->id)->where('deal_type', 'long')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $budget->used_budget, 0.01);
        $this->assertEqualsWithDelta(90_000_000.0, (float) $budget->budget, 0.01);
    }

    public function test_redistribute_budgets_splits_existing_pool_when_third_market_is_added(): void
    {
        Queue::fake();
        config()->set('trading.paper.default_quote_balance', '100000000');
        app(TradingSettingsService::class)->syncDefaults();

        $btcMarket = $this->longMarket('BTCTMN', 'BTC');
        $ethMarket = $this->longMarket('ETHTMN', 'ETH');

        $service = app(MarketBudgetService::class);
        $service->loadBudgetsFromExchange();

        $pepeMarket = $this->longMarket('PEPETMN', 'PEPE');
        $service->redistributeBudgets();

        $btcBudget = MarketBudget::query()->where('market_id', $btcMarket->id)->where('deal_type', 'long')->firstOrFail();
        $ethBudget = MarketBudget::query()->where('market_id', $ethMarket->id)->where('deal_type', 'long')->firstOrFail();
        $pepeBudget = MarketBudget::query()->where('market_id', $pepeMarket->id)->where('deal_type', 'long')->firstOrFail();

        $this->assertEqualsWithDelta(33_333_333.33, (float) $btcBudget->budget, 0.01);
        $this->assertEqualsWithDelta(33_333_333.33, (float) $ethBudget->budget, 0.01);
        $this->assertEqualsWithDelta(33_333_333.33, (float) $pepeBudget->budget, 0.01);
    }

    public function test_redistribute_budgets_returns_pool_to_remaining_active_markets_when_one_is_disabled(): void
    {
        Queue::fake();
        config()->set('trading.paper.default_quote_balance', '100000000');
        app(TradingSettingsService::class)->syncDefaults();

        $btcMarket = $this->longMarket('BTCTMN', 'BTC');
        $ethMarket = $this->longMarket('ETHTMN', 'ETH');

        $service = app(MarketBudgetService::class);
        $service->loadBudgetsFromExchange();
        $service->redistributeBudgets();

        $ethMarket->update(['long_enabled' => false]);
        $service->redistributeBudgets();

        $btcBudget = MarketBudget::query()->where('market_id', $btcMarket->id)->where('deal_type', 'long')->firstOrFail();
        $ethBudget = MarketBudget::query()->where('market_id', $ethMarket->id)->where('deal_type', 'long')->firstOrFail();

        $this->assertEqualsWithDelta(100_000_000.0, (float) $btcBudget->budget, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $ethBudget->budget, 0.01);
    }

    public function test_market_toggle_dispatches_budget_recalculation_job(): void
    {
        Queue::fake();

        $market = $this->longMarket('BTCTMN', 'BTC');
        Queue::assertPushed(\App\Jobs\Trading\RecalculateMarketBudgetsJob::class);

        Queue::fake();
        $market->update(['long_enabled' => false]);
        Queue::assertPushed(\App\Jobs\Trading\RecalculateMarketBudgetsJob::class);
    }

    private function longMarket(string $symbol, string $baseAsset): Market
    {
        return Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => $symbol,
            'base_asset' => $baseAsset,
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '1',
            'last_price' => '1000000000',
            'is_active' => true,
            'long_enabled' => true,
            'short_enabled' => false,
        ]);
    }
}
