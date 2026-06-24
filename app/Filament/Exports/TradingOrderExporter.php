<?php

namespace App\Filament\Exports;

use App\Models\TradingOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class TradingOrderExporter extends BaseExporter
{
    protected static ?string $model = TradingOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('market.symbol')->label('Market'),
            ExportColumn::make('deal_id'),
            ExportColumn::make('exchange'),
            ExportColumn::make('symbol'),
            ExportColumn::make('client_id'),
            ExportColumn::make('external_id'),
            ExportColumn::make('mode'),
            ExportColumn::make('side'),
            ExportColumn::make('type'),
            ExportColumn::make('status'),
            ExportColumn::make('price'),
            ExportColumn::make('amount'),
            ExportColumn::make('filled_amount'),
            ExportColumn::make('quote_amount'),
            ExportColumn::make('tick_offset'),
            ExportColumn::make('last_checked_at'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your order export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
