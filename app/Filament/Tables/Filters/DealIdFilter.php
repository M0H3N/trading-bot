<?php

namespace App\Filament\Tables\Filters;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

class DealIdFilter
{
    public static function make(string $column = 'deal_id'): Filter
    {
        return Filter::make('deal_id')
            ->label('Deal ID')
            ->schema([
                TextInput::make('value')
                    ->label('Deal ID')
                    ->numeric()
                    ->minValue(1),
            ])
            ->query(function (Builder $query, array $data) use ($column): void {
                if (blank($data['value'] ?? null)) {
                    return;
                }

                $query->where($column, $data['value']);
            })
            ->indicateUsing(function (array $data): array {
                if (blank($data['value'] ?? null)) {
                    return [];
                }

                return [Indicator::make('Deal ID: '.$data['value'])];
            });
    }
}
