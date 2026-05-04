<?php

namespace App\Filament\Resources\Deals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('market.symbol')->label('Market')->sortable(),
                TextColumn::make('mode')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('entry_average_price'),
                TextColumn::make('entry_amount'),
                TextColumn::make('realized_pnl')->sortable(),
                TextColumn::make('realized_pnl_percent')->suffix('%')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
