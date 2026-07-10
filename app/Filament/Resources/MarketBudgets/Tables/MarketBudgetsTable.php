<?php

namespace App\Filament\Resources\MarketBudgets\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketBudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('market.symbol')->label('Market')->sortable()->searchable(),
                TextColumn::make('deal_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => $state === 'short' ? 'warning' : 'info'),
                TextColumn::make('budget_asset')->label('Asset'),
                TextColumn::make('budget')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('used_budget')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('available_budget')
                    ->label('Available')
                    ->numeric(decimalPlaces: 2)
                    ->state(fn ($record): float => $record->availableBudget()),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('market.symbol');
    }
}
