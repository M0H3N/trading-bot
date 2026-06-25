<?php

namespace App\Filament\Widgets;

use App\Domain\Trading\Services\UnexitedPositionService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UnexitedPositionsWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Unexited positions by base asset')
            ->query(fn (): Builder => app(UnexitedPositionService::class)->aggregatedByBaseAssetQuery())
            ->columns([
                TextColumn::make('base_asset')->label('Base asset')->sortable(),
                TextColumn::make('total_unexited_amount')
                    ->label('Unexited amount')
                    ->numeric(decimalPlaces: 8),
                TextColumn::make('unrealized_value_tmn')
                    ->label('Value (TMN)')
                    ->numeric(decimalPlaces: 2),
            ])
            ->defaultSort('base_asset')
            ->defaultKeySort(false)
            ->paginated(false);
    }
}
