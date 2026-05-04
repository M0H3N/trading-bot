<?php

namespace App\Filament\Resources\HttpLogs\Tables;

use App\Models\HttpLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HttpLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('exchange')->badge()->searchable(),
                TextColumn::make('scope')->badge()->searchable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('status_code')->sortable(),
                TextColumn::make('duration_ms')->suffix(' ms')->sortable(),
                TextColumn::make('url')->limit(60)->searchable(),
                TextColumn::make('request_body')
                    ->label('Request body')
                    ->formatStateUsing(fn (mixed $state): string => HttpLog::formatRequestBodyForDisplay($state))
                    ->limit(90)
                    ->wrap()
                    ->tooltip(fn (HttpLog $record): ?string => HttpLog::formatRequestBodyForTooltip($record->request_body)),
                TextColumn::make('error')->limit(40),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make()]);
    }
}
