<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('symbol')->searchable()->sortable(),
                TextColumn::make('side')->badge(),
                TextColumn::make('mode')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('price')->sortable(),
                TextColumn::make('amount'),
                TextColumn::make('filled_amount'),
                TextColumn::make('client_id')->copyable()->limit(18),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
