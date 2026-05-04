<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\DTO\OrderBook;
use RuntimeException;

class OrderBookPricingService
{
    public function averagePriceOfDepth(OrderBook $book, string $side ,string $usdtTmnPrice, string $depthUsd): string
    {
        if(!in_array($side, ['bids', 'asks'], )){
            throw new RuntimeException("Side [{$side}] does not exist in Wallex response.");
        }

        $targetTmn = (float) $usdtTmnPrice * (float) $depthUsd;

        $remainingTmn = $targetTmn;
        $totalAmount = 0.0;
        $totalQuote = 0.0;

        foreach ($book->$side as $level) {
            if ($remainingTmn <= 0) {
                break;
            }

            $levelQuote = $level->notional();
            $takenQuote = min($remainingTmn, $levelQuote);
            $takenAmount = $takenQuote / (float) $level->price;

            $totalQuote += $takenQuote;
            $totalAmount += $takenAmount;
            $remainingTmn -= $takenQuote;
        }

        if ($totalAmount <= 0 || $totalQuote < $targetTmn) {
            throw new RuntimeException('Order book depth is insufficient for configured USD depth.');
        }

        return number_format($totalQuote / $totalAmount, 12, '.', '');
    }

    public function percentDifference(string $price, string $reference): string
    {
        if ((float) $reference <= 0) {
            return '0';
        }

        return number_format(abs(((float) $price - (float) $reference) / (float) $reference) * 100, 8, '.', '');
    }

    public function hasAnyOrderAbove(OrderBook $book, string $ourPrice, string $thresholdTmn): bool
    {
        foreach ($book->asks as $level) {
            if ((float) $level->price >= (float) $ourPrice) {
                continue;
            }

            if ($level->notional() >= (float) $thresholdTmn) {
                return true;
            }
        }

        return false;
    }
}
