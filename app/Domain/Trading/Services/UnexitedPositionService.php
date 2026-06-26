<?php

namespace App\Domain\Trading\Services;

use App\Models\Deal;
use Filament\Support\ArrayRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnexitedPositionService
{
    public function aggregatedByBaseAssetQuery(): Builder
    {
        return Deal::query()
            ->join('markets', 'markets.id', '=', 'deals.market_id')
            ->where('deals.unexited_amount', '>', 0)
            ->groupBy('markets.base_asset')
            ->select([
                DB::raw('MIN(deals.id) as id'),
                DB::raw('markets.base_asset as base_asset'),
                DB::raw('SUM(deals.unexited_amount) as total_unexited_amount'),
                DB::raw('SUM(deals.unexited_amount * markets.last_price) as unrealized_value_tmn'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function adjustedAggregatedByBaseAsset(): Collection
    {
        $baselines = app(PnlResetService::class)->unexitedBaselines();
        $keyName = ArrayRecord::getKeyName();

        if ($baselines === []) {
            return $this->aggregatedByBaseAssetQuery()
                ->get()
                ->map(fn (Deal $row): array => [
                    $keyName => (string) $row->base_asset,
                    'base_asset' => (string) $row->base_asset,
                    'total_unexited_amount' => (float) $row->total_unexited_amount,
                    'unrealized_value_tmn' => (float) $row->unrealized_value_tmn,
                ])
                ->values();
        }

        return $this->aggregatedByBaseAssetQuery()
            ->get()
            ->map(function (Deal $row) use ($baselines, $keyName): ?array {
                $baseAsset = (string) $row->base_asset;
                $baselineAmount = $baselines[$baseAsset]['amount'] ?? 0.0;
                $currentAmount = (float) $row->total_unexited_amount;
                $deltaAmount = $currentAmount - $baselineAmount;

                if (abs($deltaAmount) < 1e-12) {
                    return null;
                }

                $averagePrice = $currentAmount > 1e-12
                    ? (float) $row->unrealized_value_tmn / $currentAmount
                    : 0.0;

                return [
                    $keyName => $baseAsset,
                    'base_asset' => $baseAsset,
                    'total_unexited_amount' => $deltaAmount,
                    'unrealized_value_tmn' => $deltaAmount * $averagePrice,
                ];
            })
            ->filter()
            ->values();
    }

    public function adjustedUnrealizedValueTmn(): float
    {
        return (float) $this->adjustedAggregatedByBaseAsset()->sum('unrealized_value_tmn');
    }

    public function totalUnrealizedValueTmn(): float
    {
        return (float) Deal::query()
            ->join('markets', 'markets.id', '=', 'deals.market_id')
            ->where('deals.unexited_amount', '>', 0)
            ->sum(DB::raw('deals.unexited_amount * markets.last_price'));
    }
}
