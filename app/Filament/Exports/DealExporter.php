<?php

namespace App\Filament\Exports;

use App\Models\Deal;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class DealExporter extends BaseExporter
{
    protected static ?string $model = Deal::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('market.symbol')->label('Market'),
            ExportColumn::make('mode'),
            ExportColumn::make('status'),
            ExportColumn::make('entry_average_price'),
            ExportColumn::make('entry_amount'),
            ExportColumn::make('exit_average_price'),
            ExportColumn::make('exit_amount'),
            ExportColumn::make('realized_pnl'),
            ExportColumn::make('realized_pnl_percent'),
            ExportColumn::make('exited'),
            ExportColumn::make('unexited_amount'),
            ExportColumn::make('opened_at'),
            ExportColumn::make('closed_at'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your deal export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
