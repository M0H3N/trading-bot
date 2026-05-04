<?php

namespace App\Filament\Resources\Balances;

use App\Filament\Resources\Balances\Pages\CreateBalance;
use App\Filament\Resources\Balances\Pages\EditBalance;
use App\Filament\Resources\Balances\Pages\ListBalances;
use App\Filament\Resources\Balances\Schemas\BalanceForm;
use App\Filament\Resources\Balances\Tables\BalancesTable;
use App\Models\Balance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BalanceResource extends Resource
{
    protected static ?string $model = Balance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Balances';

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    public static function form(Schema $schema): Schema
    {
        return BalanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BalancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBalances::route('/'),
            'create' => CreateBalance::route('/create'),
            'edit' => EditBalance::route('/{record}/edit'),
        ];
    }
}
