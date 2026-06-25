<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Filament\Exports\DealExporter;
use App\Filament\Tables\Filters\DealIdFilter;
use App\Domain\Trading\Services\TradeRecorder;
use App\Jobs\Trading\CancelDealExitOrdersJob;
use App\Models\Deal;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Deal ID')->sortable()->copyable(),
                TextColumn::make('market.symbol')->label('Market')->sortable(),
                TextColumn::make('mode')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'manually_closed', 'stop_loss' => 'danger',
                        'closed' => 'success',
                        'expired' => 'gray',
                        'entered', 'exiting' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('entry_average_price'),
                TextColumn::make('entry_amount'),
                TextColumn::make('realized_pnl')->sortable(),
                TextColumn::make('realized_pnl_percent')->suffix('%')->sortable(),
                TextColumn::make('exited')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('unexited_amount')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                DealIdFilter::make('id'),
                SelectFilter::make('status')
                    ->options(self::statusOptions())
                    ->multiple(),
                TernaryFilter::make('exited')
                    ->label('Exited')
                    ->placeholder('All deals')
                    ->trueLabel('Exited')
                    ->falseLabel('Not exited'),
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
            ->recordActions([
                Action::make('closeManually')
                    ->label('Close manually')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Close deal manually?')
                    ->modalDescription('This deal will be marked as manually closed. Exit management will stop and it will be treated like a closed deal.')
                    ->modalSubmitActionLabel('Yes, close deal')
                    ->visible(fn (Deal $record): bool => ! $record->isClosed())
                    ->action(function (Deal $record, TradeRecorder $recorder): void {
                        $record->forceFill([
                            'status' => 'manually_closed',
                            'closed_at' => now(),
                        ])->save();

                        $recorder->recalculateDeal($record->refresh());

                        CancelDealExitOrdersJob::dispatch($record->id);

                        Notification::make()
                            ->title('Deal closed manually')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordUrl(null);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'opening' => 'Opening',
            'entered' => 'Entered',
            'exiting' => 'Exiting',
            'stop_loss' => 'Stop Loss',
            'closed' => 'Closed',
            'expired' => 'Expired',
            'manually_closed' => 'Manually closed',
        ];
    }
}
