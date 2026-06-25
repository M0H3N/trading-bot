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

        return $this->aggregatedByBaseAssetQuery()
            ->get()
            ->map(function (Deal $row) use ($baselines, $keyName): array {
                $baseAsset = (string) $row->base_asset;
                $baseline = $baselines[$baseAsset] ?? ['amount' => 0.0, 'value_tmn' => 0.0];

                return [
                    $keyName => $baseAsset,
                    'base_asset' => $baseAsset,
                    'total_unexited_amount' => max(0.0, (float) $row->total_unexited_amount - $baseline['amount']),
                    'unrealized_value_tmn' => max(0.0, (float) $row->unrealized_value_tmn - $baseline['value_tmn']),
                ];
            })
            ->filter(fn (array $row): bool => $row['total_unexited_amount'] > 1e-12 || $row['unrealized_value_tmn'] > 0.01)
            ->values();
    }

    public function totalUnrealizedValueTmn(): float
    {
        return (float) Deal::query()
            ->join('markets', 'markets.id', '=', 'deals.market_id')
            ->where('deals.unexited_amount', '>', 0)
            ->sum(DB::raw('deals.unexited_amount * markets.last_price'));
    }
}
