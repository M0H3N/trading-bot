<?php

namespace App\Filament\Resources\Markets;

use App\Filament\Resources\Markets\Pages\CreateMarket;
use App\Filament\Resources\Markets\Pages\EditMarket;
use App\Filament\Resources\Markets\Pages\ListMarkets;
use App\Filament\Resources\Markets\Schemas\MarketForm;
use App\Filament\Resources\Markets\Tables\MarketsTable;
use App\Models\Market;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketResource extends Resource
{
    protected static ?string $model = Market::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Markets';

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    public static function form(Schema $schema): Schema
    {
        return MarketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarkets::route('/'),
            'create' => CreateMarket::route('/create'),
            'edit' => EditMarket::route('/{record}/edit'),
        ];
    }
}
