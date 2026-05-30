<?php

namespace App\Domain\Trading\Services;

use Illuminate\Support\Arr;

final class EntryOrderPayload
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public static function filledAmount(array $raw): ?string
    {
        $result = Arr::get($raw, 'result', $raw);

        $qty = Arr::get($result, 'executedQty');

        if (! is_numeric($qty) || (float) $qty < 0) {
            return null;
        }

        return (string) $qty;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function averagePrice(array $raw): ?string
    {
        $result = Arr::get($raw, 'result', $raw);

        $executedPrice = Arr::get($result, 'executedPrice');
        if (is_numeric($executedPrice) && (float) $executedPrice > 0) {
            return self::formatDecimal((float) $executedPrice);
        }

        $fromFills = self::weightedAverageFromFills($result);
        if ($fromFills !== null) {
            return $fromFills;
        }

        return self::averageFromExecutedTotals($result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function weightedAverageFromFills(array $result): ?string
    {
        $fills = Arr::get($result, 'fills', []);
        if (! is_array($fills) || $fills === []) {
            return null;
        }

        $totalQty = 0.0;
        $totalQuote = 0.0;

        foreach ($fills as $fill) {
            if (! is_array($fill)) {
                continue;
            }

            $qty = (float) (Arr::get($fill, 'quantity') ?? 0);
            $price = (float) (Arr::get($fill, 'price') ?? 0);

            if ($qty <= 0 || $price <= 0) {
                continue;
            }

            $totalQty += $qty;
            $totalQuote += $price * $qty;
        }

        if ($totalQty <= 0) {
            return null;
        }

        return self::formatDecimal($totalQuote / $totalQty);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function averageFromExecutedTotals(array $result): ?string
    {
        $qty = (float) (Arr::get($result, 'executedQty') ?? 0);
        $sum = (float) (Arr::get($result, 'executedSum') ?? Arr::get($result, 'sum') ?? 0);

        if ($qty <= 0 || $sum <= 0) {
            return null;
        }

        return self::formatDecimal($sum / $qty);
    }

    protected static function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 12, '.', ''), '0'), '.') ?: '0';
    }
}
