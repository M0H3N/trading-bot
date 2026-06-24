<?php

namespace App\Filament\Resources\Trades\Tables;

use App\Filament\Exports\TradeExporter;
use App\Filament\Tables\Filters\DealIdFilter;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('deal_id')->label('Deal ID')->sortable()->copyable(),
                TextColumn::make('market.symbol')->label('Market'),
                TextColumn::make('side')->badge(),
                TextColumn::make('price'),
                TextColumn::make('amount'),
                TextColumn::make('quote_amount'),
                TextColumn::make('filled_at')->dateTime()->sortable(),
            ])
            ->filters([
                DealIdFilter::make(),
                SelectFilter::make('market')
                    ->relationship('market', 'symbol')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(TradeExporter::class)
                    ->formats([
                        ExportFormat::Csv,
                        ExportFormat::Xlsx,
                    ]),
            ])
            ->recordUrl(null);
    }
}
