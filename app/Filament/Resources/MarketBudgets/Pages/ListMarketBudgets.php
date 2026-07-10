<?php

namespace App\Filament\Resources\MarketBudgets\Pages;

use App\Domain\Trading\Services\MarketBudgetService;
use App\Filament\Resources\MarketBudgets\MarketBudgetResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMarketBudgets extends ListRecords
{
    protected static string $resource = MarketBudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Reset budgets')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reset market budgets')
                ->modalDescription('This zeros all used budgets and reloads allocated budgets from the exchange balance API.')
                ->action(function (MarketBudgetService $service): void {
                    $service->reset();

                    Notification::make()
                        ->title('Market budgets reset')
                        ->success()
                        ->send();
                }),
        ];
    }
}
