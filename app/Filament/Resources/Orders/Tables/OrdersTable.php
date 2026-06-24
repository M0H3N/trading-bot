<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Exports\TradingOrderExporter;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('market.symbol')->label('Market')->sortable(),
                TextColumn::make('side')->badge(),
                TextColumn::make('mode')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('price')->sortable(),
                TextColumn::make('amount'),
                TextColumn::make('filled_amount'),
                TextColumn::make('client_id')->copyable()->limit(18),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('market')
                    ->relationship('market', 'symbol')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(TradingOrderExporter::class)
                    ->formats([
                        ExportFormat::Csv,
                        ExportFormat::Xlsx,
                    ]),
            ])
            ->recordActions([EditAction::make()]);
    }
}
