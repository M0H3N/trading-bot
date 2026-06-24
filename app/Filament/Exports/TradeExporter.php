<?php

namespace App\Filament\Exports;

use App\Models\Trade;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class TradeExporter extends BaseExporter
{
    protected static ?string $model = Trade::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('market.symbol')->label('Market'),
            ExportColumn::make('deal_id'),
            ExportColumn::make('order_id'),
            ExportColumn::make('exchange_trade_id'),
            ExportColumn::make('mode'),
            ExportColumn::make('side'),
            ExportColumn::make('price'),
            ExportColumn::make('amount'),
            ExportColumn::make('quote_amount'),
            ExportColumn::make('fee'),
            ExportColumn::make('fee_asset'),
            ExportColumn::make('filled_at'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your trade export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
