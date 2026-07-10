<?php

namespace App\Filament\Resources\MarketBudgets;

use App\Filament\Resources\MarketBudgets\Pages\ListMarketBudgets;
use App\Filament\Resources\MarketBudgets\Tables\MarketBudgetsTable;
use App\Models\MarketBudget;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MarketBudgetResource extends Resource
{
    protected static ?string $model = MarketBudget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Market Budgets';

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    public static function table(Table $table): Table
    {
        return MarketBudgetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketBudgets::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
