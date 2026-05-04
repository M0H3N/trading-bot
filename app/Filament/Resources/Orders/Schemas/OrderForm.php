<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Deal;
use App\Models\Market;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->schema([
                Select::make('market_id')->options(fn () => Market::query()->pluck('symbol', 'id'))->required()->searchable(),
                Select::make('deal_id')->options(fn () => Deal::query()->pluck('id', 'id'))->searchable(),
                TextInput::make('client_id')->required(),
                Select::make('mode')->options(['paper' => 'Paper', 'live' => 'Live'])->required(),
                Select::make('side')->options(['buy' => 'Buy', 'sell' => 'Sell'])->required(),
                Select::make('status')->options(['pending' => 'Pending', 'open' => 'Open', 'partially_filled' => 'Partially Filled', 'filled' => 'Filled', 'cancelled' => 'Cancelled', 'failed' => 'Failed'])->required(),
                TextInput::make('price')->numeric()->required(),
                TextInput::make('amount')->numeric()->required(),
                TextInput::make('filled_amount')->numeric()->default(0),
            ])->columns(2),
        ]);
    }
}
