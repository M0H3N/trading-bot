<?php

namespace App\Filament\Resources\Trades\Tables;

use App\Filament\Exports\TradeExporter;
use Filament\Actions\EditAction;
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
                TextColumn::make('market.symbol')->label('Market'),
                TextColumn::make('deal_id')->sortable(),
                TextColumn::make('side')->badge(),
                TextColumn::make('price'),
                TextColumn::make('amount'),
                TextColumn::make('quote_amount'),
                TextColumn::make('filled_at')->dateTime()->sortable(),
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
                    ->exporter(TradeExporter::class)
                    ->formats([
                        ExportFormat::Csv,
                        ExportFormat::Xlsx,
                    ]),
            ])
            ->recordActions([EditAction::make()]);
    }
}
