<?php

namespace App\Domain\Trading\Services;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Builder;
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

    public function totalUnrealizedValueTmn(): float
    {
        return (float) Deal::query()
            ->join('markets', 'markets.id', '=', 'deals.market_id')
            ->where('deals.unexited_amount', '>', 0)
            ->sum(DB::raw('deals.unexited_amount * markets.last_price'));
    }
}
