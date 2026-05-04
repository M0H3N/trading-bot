<?php

namespace App\Filament\Resources\HttpLogs;

use App\Filament\Resources\HttpLogs\Pages\ListHttpLogs;
use App\Filament\Resources\HttpLogs\Schemas\HttpLogForm;
use App\Filament\Resources\HttpLogs\Tables\HttpLogsTable;
use App\Models\HttpLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HttpLogResource extends Resource
{
    protected static ?string $model = HttpLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'HTTP Logs';

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    public static function form(Schema $schema): Schema
    {
        return HttpLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HttpLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHttpLogs::route('/'),
        ];
    }
}
