<?php

namespace App\Filament\Resources\Markets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MarketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Market')->schema([
                TextInput::make('exchange')->default('wallex')->required(),
                TextInput::make('symbol')->required()->maxLength(32),
                TextInput::make('base_asset')->required()->maxLength(16),
                Select::make('quote_asset')->options(['TMN' => 'TMN', 'USDT' => 'USDT'])->default('TMN')->required(),
                TextInput::make('tick_size')->numeric()->default(1)->required(),
                TextInput::make('step_size')->numeric()->default(1)->required(),
                TextInput::make('min_order_amount')->numeric()->default(0)->required(),
                Toggle::make('is_active')->label('Active'),
            ])->columns(2),
        ]);
    }
}
