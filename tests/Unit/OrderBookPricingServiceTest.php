<?php

namespace Tests\Unit;

use App\Domain\Exchange\DTO\OrderBook;
use App\Domain\Exchange\DTO\OrderBookLevel;
use App\Domain\Trading\Services\OrderBookPricingService;
use PHPUnit\Framework\TestCase;

class OrderBookPricingServiceTest extends TestCase
{
    public function test_it_calculates_bid_average_until_usd_depth(): void
    {
        $book = new OrderBook('BTCTMN', [
            new OrderBookLevel('100', '10'),
            new OrderBookLevel('90', '20'),
        ], []);

        $average = (new OrderBookPricingService)->averagePriceOfDepth($book, '10', '200');

        $this->assertSame('94.736842105263', $average);
    }

    public function test_it_detects_large_blocking_bid_above_our_order(): void
    {
        $book = new OrderBook('BTCTMN', [
            new OrderBookLevel('105', '200000'),
            new OrderBookLevel('100', '1'),
        ], []);

        $this->assertTrue((new OrderBookPricingService)->hasAnyOrderAbove($book, '100', '15000000'));
    }
}
