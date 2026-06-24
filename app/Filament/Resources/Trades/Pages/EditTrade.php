<?php

namespace App\Filament\Resources\Trades\Pages;

use App\Filament\Resources\Trades\TradeResource;
use Filament\Resources\Pages\EditRecord;

class EditTrade extends EditRecord
{
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
