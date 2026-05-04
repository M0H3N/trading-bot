<?php

namespace App\Filament\Resources\HttpLogs\Schemas;

use App\Models\HttpLog;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HttpLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('HTTP Log')->schema([
                TextInput::make('exchange')->disabled(),
                TextInput::make('scope')->disabled(),
                TextInput::make('method')->disabled(),
                TextInput::make('status_code')->disabled(),
                TextInput::make('duration_ms')->disabled(),
                Textarea::make('url')->disabled()->columnSpanFull(),
                Textarea::make('request_body')
                    ->label('Request body')
                    ->disabled()
                    ->columnSpanFull()
                    ->formatStateUsing(fn (mixed $state): string => HttpLog::formatRequestBodyForDisplay($state)),
                Textarea::make('response_body')->disabled()->columnSpanFull(),
                Textarea::make('error')->disabled()->columnSpanFull(),
            ])->columns(3),
        ]);
    }
}
