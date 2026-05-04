<?php

namespace App\Filament\Resources\Balances\Pages;

use App\Filament\Resources\Balances\BalanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBalances extends ListRecords
{
    protected static string $resource = BalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
