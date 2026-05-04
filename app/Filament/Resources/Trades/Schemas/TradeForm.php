<?php

namespace App\Filament\Resources\Trades\Schemas;

use App\Models\Deal;
use App\Models\Market;
use App\Models\TradingOrder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Trade')->schema([
                Select::make('market_id')->options(fn () => Market::query()->pluck('symbol', 'id'))->required()->searchable(),
                Select::make('deal_id')->options(fn () => Deal::query()->pluck('id', 'id'))->required()->searchable(),
                Select::make('order_id')->options(fn () => TradingOrder::query()->pluck('client_id', 'id'))->required()->searchable(),
                Select::make('side')->options(['buy' => 'Buy', 'sell' => 'Sell'])->required(),
                TextInput::make('price')->numeric()->required(),
                TextInput::make('amount')->numeric()->required(),
                TextInput::make('quote_amount')->numeric(),
                TextInput::make('fee')->numeric(),
                TextInput::make('fee_asset'),
            ])->columns(3),
        ]);
    }
}
