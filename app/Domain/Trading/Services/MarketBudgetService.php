<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\ExchangeManager;
use App\Models\Deal;
use App\Models\Market;
use App\Models\MarketBudget;
use Illuminate\Support\Facades\DB;

class MarketBudgetService
{
    private const INITIAL_BALANCE_PERCENT = 0.99;

    public function __construct(
        private readonly ExchangeManager $exchanges,
        private readonly TradingSettingsService $settings,
    ) {}

    public function initialize(): void
    {
        $this->ensureBudgetRows();
        $this->loadBudgetsFromExchange(useInitialPercent: true);
        $this->syncAllUsedBudgets();
    }

    public function reset(): void
    {
        MarketBudget::query()->update(['used_budget' => 0]);
        $this->loadBudgetsFromExchange(useInitialPercent: false);
    }

    public function loadBudgetsFromExchange(bool $useInitialPercent = false): void
    {
        $this->ensureBudgetRows();

        $mode = $this->settings->mode();
        $factor = $useInitialPercent ? self::INITIAL_BALANCE_PERCENT : 1.0;

        $this->loadLongBudgetsFromExchange($mode, $factor);
        $this->loadShortBudgetsFromExchange($mode, $factor);
    }

    public function redistributeBudgets(float $factor = 1.0): void
    {
        $this->ensureBudgetRows();
        $this->redistributeLongBudgets($factor);
        $this->redistributeShortBudgets($factor);
    }

    public function ensureBudgetRows(): void
    {
        Market::query()->each(function (Market $market): void {
            MarketBudget::query()->firstOrCreate(
                ['market_id' => $market->id, 'deal_type' => Deal::DIRECTION_LONG],
                ['budget_asset' => $market->quote_asset, 'budget' => 0, 'used_budget' => 0],
            );

            MarketBudget::query()->firstOrCreate(
                ['market_id' => $market->id, 'deal_type' => Deal::DIRECTION_SHORT],
                ['budget_asset' => $market->base_asset, 'budget' => 0, 'used_budget' => 0],
            );
        });
    }

    public function dealExposure(Deal $deal): float
    {
        if ($deal->isClosed()) {
            return 0.0;
        }

        if ($deal->direction === Deal::DIRECTION_LONG) {
            return max(0.0, (float) $deal->entry_quote - (float) $deal->exit_quote);
        }

        return max(0.0, (float) $deal->entry_amount - (float) $deal->exit_amount);
    }

    public function applyDealBudgetDelta(Deal $deal, float $previousExposure): void
    {
        $delta = $this->dealExposure($deal) - $previousExposure;

        $this->adjustUsedBudget($deal->market_id, $deal->direction, $delta);
    }

    public function adjustUsedBudget(int $marketId, string $dealType, float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }

        $this->ensureBudgetRows();

        $formattedDelta = number_format($delta, 12, '.', '');

        MarketBudget::query()
            ->where('market_id', $marketId)
            ->where('deal_type', $dealType)
            ->update([
                'used_budget' => DB::raw(
                    'CASE WHEN used_budget + '.$formattedDelta.' < 0 THEN 0 ELSE used_budget + '.$formattedDelta.' END'
                ),
            ]);
    }

    public function syncAllUsedBudgets(): void
    {
        MarketBudget::query()->update(['used_budget' => 0]);

        Deal::query()->open()->each(function (Deal $deal): void {
            $this->adjustUsedBudget(
                $deal->market_id,
                $deal->direction,
                $this->dealExposure($deal),
            );
        });
    }

    public function availableForEntry(Market $market, string $direction): float
    {
        $budget = MarketBudget::query()
            ->where('market_id', $market->id)
            ->where('deal_type', $direction)
            ->first();

        if (! $budget) {
            return 0.0;
        }

        return $budget->availableBudget();
    }

    protected function loadLongBudgetsFromExchange(string $mode, float $factor): void
    {
        $activeMarkets = Market::query()->active()->where('long_enabled', true)->get();
        $allocations = [];

        foreach ($activeMarkets->groupBy(fn (Market $market): string => $market->exchange.'|'.$market->quote_asset) as $groupKey => $markets) {
            /** @var Market $sample */
            $sample = $markets->first();
            $client = $this->exchanges->client($sample->exchange, $mode);
            $balance = (float) $client->getBalance($sample->quote_asset)->available;
            $perMarket = $markets->count() > 0 ? ($balance * $factor) / $markets->count() : 0.0;

            foreach ($markets as $market) {
                $allocations[$market->id] = $perMarket;
            }
        }

        MarketBudget::query()->long()->with('market')->each(function (MarketBudget $budget) use ($allocations): void {
            $budget->forceFill([
                'budget_asset' => $budget->market->quote_asset,
                'budget' => number_format($allocations[$budget->market_id] ?? 0.0, 12, '.', ''),
            ])->save();
        });
    }

    protected function loadShortBudgetsFromExchange(string $mode, float $factor): void
    {
        $activeMarkets = Market::query()->active()->where('short_enabled', true)->get();
        $allocations = [];

        foreach ($activeMarkets->groupBy(fn (Market $market): string => $market->exchange.'|'.$market->base_asset) as $groupKey => $markets) {
            /** @var Market $sample */
            $sample = $markets->first();
            $client = $this->exchanges->client($sample->exchange, $mode);
            $balance = (float) $client->getBalance($sample->base_asset)->available;
            $perMarket = $markets->count() > 0 ? ($balance * $factor) / $markets->count() : 0.0;

            foreach ($markets as $market) {
                $allocations[$market->id] = $perMarket;
            }
        }

        MarketBudget::query()->short()->with('market')->each(function (MarketBudget $budget) use ($allocations): void {
            $budget->forceFill([
                'budget_asset' => $budget->market->base_asset,
                'budget' => number_format($allocations[$budget->market_id] ?? 0.0, 12, '.', ''),
            ])->save();
        });
    }

    protected function redistributeLongBudgets(float $factor): void
    {
        $activeMarketIds = Market::query()->active()->where('long_enabled', true)->pluck('id');

        foreach (Market::query()->get()->groupBy(fn (Market $market): string => $market->exchange.'|'.$market->quote_asset) as $markets) {
            $marketIds = $markets->pluck('id');
            $pool = (float) MarketBudget::query()
                ->long()
                ->whereIn('market_id', $marketIds)
                ->sum('budget');

            $activeInGroup = $markets->filter(fn (Market $market): bool => $activeMarketIds->contains($market->id));
            $perMarket = $activeInGroup->count() > 0 ? ($pool / $activeInGroup->count()) * $factor : 0.0;

            foreach ($markets as $market) {
                $allocated = $activeInGroup->contains('id', $market->id) ? $perMarket : 0.0;

                MarketBudget::query()
                    ->where('market_id', $market->id)
                    ->where('deal_type', Deal::DIRECTION_LONG)
                    ->update([
                        'budget_asset' => $market->quote_asset,
                        'budget' => number_format($allocated, 12, '.', ''),
                    ]);
            }
        }
    }

    protected function redistributeShortBudgets(float $factor): void
    {
        $activeMarketIds = Market::query()->active()->where('short_enabled', true)->pluck('id');

        foreach (Market::query()->get()->groupBy(fn (Market $market): string => $market->exchange.'|'.$market->base_asset) as $markets) {
            $marketIds = $markets->pluck('id');
            $pool = (float) MarketBudget::query()
                ->short()
                ->whereIn('market_id', $marketIds)
                ->sum('budget');

            $activeInGroup = $markets->filter(fn (Market $market): bool => $activeMarketIds->contains($market->id));
            $perMarket = $activeInGroup->count() > 0 ? ($pool / $activeInGroup->count()) * $factor : 0.0;

            foreach ($markets as $market) {
                $allocated = $activeInGroup->contains('id', $market->id) ? $perMarket : 0.0;

                MarketBudget::query()
                    ->where('market_id', $market->id)
                    ->where('deal_type', Deal::DIRECTION_SHORT)
                    ->update([
                        'budget_asset' => $market->base_asset,
                        'budget' => number_format($allocated, 12, '.', ''),
                    ]);
            }
        }
    }
}
