<?php

namespace App\Filament\Resources\Deals\Schemas;

use App\Models\Market;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Deal')->schema([
                Select::make('market_id')->options(fn () => Market::query()->pluck('symbol', 'id'))->required()->searchable(),
                Select::make('mode')->options(['paper' => 'Paper', 'live' => 'Live'])->required(),
                Select::make('status')->options(['opening' => 'Opening', 'entered' => 'Entered', 'exiting' => 'Exiting', 'stop_loss' => 'Stop Loss', 'closed' => 'Closed'])->required(),
                TextInput::make('entry_average_price')->numeric(),
                TextInput::make('entry_amount')->numeric(),
                TextInput::make('exit_average_price')->numeric(),
                TextInput::make('exit_amount')->numeric(),
                TextInput::make('realized_pnl')->numeric(),
                TextInput::make('realized_pnl_percent')->numeric(),
            ])->columns(3),
        ]);
    }
}
