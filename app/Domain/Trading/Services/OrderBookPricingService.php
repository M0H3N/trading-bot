<?php

namespace App\Domain\Trading\Services;

use App\Domain\Exchange\DTO\OrderBook;
use RuntimeException;

class OrderBookPricingService
{
    public function averagePriceOfDepth(OrderBook $book, string $side, string $depthUsd, string $quoteAsset, ?string $usdtTmnPrice = null, bool $depthInBaseAsset = false): string
    {
        if(!in_array($side, ['bids', 'asks'], )){
            throw new RuntimeException("Side [{$side}] does not exist in Wallex response.");
        }

        if ($depthInBaseAsset) {
            return $this->averagePriceByBaseDepth($book, $side, $depthUsd);
        }

        $targetQuote = strtoupper($quoteAsset) === 'USDT'
            ? (float) $depthUsd
            : (float) $usdtTmnPrice * (float) $depthUsd;

        $remainingQuote = $targetQuote;
        $totalAmount = 0.0;
        $totalQuote = 0.0;

        foreach ($book->$side as $level) {

            $levelQuote = $level->notional();
            $takenQuote = min($remainingQuote, $levelQuote);
            $takenAmount = $takenQuote / (float) $level->price;

            $totalQuote += $takenQuote;
            $totalAmount += $takenAmount;
            $remainingQuote -= $takenQuote;

            if ($remainingQuote <= 0) {
                break;
            }
        }

        if ($totalAmount <= 0) {
            \Log::info('depth_info',[
                'depth' => $depthUsd,
                'quoteAsset' => $quoteAsset,
                'usdtTmnPrice' => $usdtTmnPrice,
                'side' => $side,
                'targetQuote' => $targetQuote,
                'totalAmount' => $totalAmount,
                'remainingQuote' => $remainingQuote,
                'orderBook' => (array)$book,
            ]);
            throw new RuntimeException("Order book depth is insufficient for configured USD depth. total amount is : {$totalAmount}}");
        }

        return number_format($totalQuote / $totalAmount, 12, '.', '');
    }

    private function averagePriceByBaseDepth(OrderBook $book, string $side, string $depthBase): string
    {
        $remainingBase = (float) $depthBase;
        $totalBase = 0.0;
        $totalQuote = 0.0;

        foreach ($book->$side as $level) {
            $levelBase = (float) $level->amount;
            $takenBase = min($remainingBase, $levelBase);

            $totalBase += $takenBase;
            $totalQuote += $takenBase * (float) $level->price;
            $remainingBase -= $takenBase;

            if ($remainingBase <= 0) {
                break;
            }
        }

        if ($totalBase <= 0) {
            throw new RuntimeException("Order book depth is insufficient for configured base depth. total base is : {$totalBase}");
        }

        return number_format($totalQuote / $totalBase, 12, '.', '');
    }

    public function percentDifference(string $price, string $reference): string
    {
        if ((float) $reference <= 0) {
            return '0';
        }


        return number_format(abs((((float) $price - (float) $reference)) / (float) $reference) * 100, 8, '.', '');
    }

    public function hasAnyOrderAbove(OrderBook $book, string $ourPrice, string $quoteAsset, string $threshold): bool
    {
        foreach ($book->bids as $level) {
            if ((float) $level->price > (float) $ourPrice && $level->notional() >= (float) $threshold) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyOrderBelow(OrderBook $book, string $ourPrice, string $quoteAsset, string $threshold): bool
    {
        foreach ($book->asks as $level) {
            if ((float) $level->price < (float) $ourPrice && $level->notional() >= (float) $threshold) {
                return true;
            }
        }

        return false;
    }
}
