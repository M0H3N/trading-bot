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
                TextColumn::make('budget')
                    ->formatStateUsing(fn ($state, $record): string => self::formatAmount($state, $record->budget_asset))
                    ->sortable(),
                TextColumn::make('used_budget')
                    ->formatStateUsing(fn ($state, $record): string => self::formatAmount($state, $record->budget_asset))
                    ->sortable(),
                TextColumn::make('available_budget')
                    ->label('Available')
                    ->state(fn ($record): float => $record->availableBudget())
                    ->formatStateUsing(fn ($state, $record): string => self::formatAmount($state, $record->budget_asset)),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('market.symbol');
    }

    protected static function formatAmount(mixed $amount, string $asset): string
    {
        $decimals = strtoupper($asset) === 'TMN' ? 2 : 8;

        return number_format((float) $amount, $decimals, '.', ',');
    }
}
