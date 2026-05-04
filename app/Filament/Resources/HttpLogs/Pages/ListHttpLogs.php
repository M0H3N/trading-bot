<?php

namespace App\Filament\Resources\HttpLogs\Pages;

use App\Filament\Resources\HttpLogs\HttpLogResource;
use Filament\Resources\Pages\ListRecords;

class ListHttpLogs extends ListRecords
{
    protected static string $resource = HttpLogResource::class;
}
