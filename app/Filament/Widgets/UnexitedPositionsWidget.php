<?php

namespace App\Filament\Widgets;

use App\Domain\Trading\Services\UnexitedPositionService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class UnexitedPositionsWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    #[On('pnl-reset')]
    public function handlePnlReset(): void
    {
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Unexited positions by base asset')
            ->records(fn (): array => app(UnexitedPositionService::class)
                ->adjustedAggregatedByBaseAsset()
                ->all())
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
