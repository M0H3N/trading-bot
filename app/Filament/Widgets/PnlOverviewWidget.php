<?php

namespace App\Filament\Widgets;

use App\Models\Deal;
use App\Models\TradingOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PnlOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Realized PnL', number_format((float) Deal::query()->sum('realized_pnl'), 2)),
            Stat::make('Open Deals', (string) Deal::query()->open()->count()),
            Stat::make('Active Orders', (string) TradingOrder::query()->active()->count()),
        ];
    }
}
