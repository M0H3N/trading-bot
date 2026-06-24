<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Filament\Exports\DealExporter;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
            ->filters([
                SelectFilter::make('market')
                    ->relationship('market', 'symbol')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(DealExporter::class)
                    ->formats([
                        ExportFormat::Csv,
                        ExportFormat::Xlsx,
                    ]),
            ])
            ->recordActions([EditAction::make()]);
    }
}
