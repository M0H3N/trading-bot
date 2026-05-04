<?php

namespace App\Filament\Resources\Balances\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BalanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Balance')->schema([
                TextInput::make('exchange')->default('wallex')->required(),
                TextInput::make('asset')->required(),
                Select::make('mode')->options(['paper' => 'Paper', 'live' => 'Live'])->required(),
                TextInput::make('available')->numeric()->required(),
                TextInput::make('locked')->numeric()->required(),
            ])->columns(2),
        ]);
    }
}
