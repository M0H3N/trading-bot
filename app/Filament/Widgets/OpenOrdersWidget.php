<?php

namespace App\Filament\Widgets;

use App\Domain\Trading\Services\CancelOpenOrderService;
use App\Jobs\Trading\CancelAllOpenOrdersJob;
use App\Models\TradingOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpenOrdersWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Open orders')
            ->query(fn (): Builder => TradingOrder::query()
                ->active()
                ->with(['market', 'deal']))
            ->columns([
                TextColumn::make('id')->label('Order ID')->sortable(),
                TextColumn::make('deal_id')->label('Deal ID')->sortable()->placeholder('—'),
                TextColumn::make('market.symbol')->label('Market')->sortable(),
                TextColumn::make('side')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'buy' ? 'success' : 'danger'),
                TextColumn::make('mode')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('price')->sortable(),
                TextColumn::make('amount'),
                TextColumn::make('filled_amount'),
                TextColumn::make('client_id')->copyable()->limit(18),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('cancelAll')
                    ->label('Cancel all')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel all open orders?')
                    ->modalDescription('Every open buy and sell order will be cancelled on the exchange. This runs in the background queue.')
                    ->modalSubmitActionLabel('Yes, cancel all')
                    ->visible(fn (): bool => TradingOrder::query()->active()->exists())
                    ->action(function (): void {
                        CancelAllOpenOrdersJob::dispatch();

                        Notification::make()
                            ->title('Cancel all orders queued')
                            ->body('Open orders will be cancelled in the background.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this order?')
                    ->modalSubmitActionLabel('Yes, cancel order')
                    ->action(function (TradingOrder $record, CancelOpenOrderService $service): void {
                        $service->cancel($record);

                        Notification::make()
                            ->title('Order cancelled')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }
}
