<?php

namespace App\Filament\Resources\Balances\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BalancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exchange'),
                TextColumn::make('asset')->searchable(),
                TextColumn::make('mode')->badge(),
                TextColumn::make('available'),
                TextColumn::make('locked'),
                TextColumn::make('synced_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
