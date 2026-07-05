<?php

namespace Tests\Unit;

use App\Domain\Exchange\DTO\OrderBook;
use App\Domain\Exchange\DTO\OrderBookLevel;
use PHPUnit\Framework\TestCase;

class OrderBookTest extends TestCase
{
    public function test_it_returns_first_bid_with_notional_at_or_above_threshold(): void
    {
        $book = new OrderBook('BTCTMN', [
            new OrderBookLevel('100', '1'),
            new OrderBookLevel('99', '200000'),
            new OrderBookLevel('98', '500000'),
        ], []);

        $bid = $book->firstBidWithMinNotional(15000000);

        $this->assertNotNull($bid);
        $this->assertSame('99', $bid->price);
    }

    public function test_it_returns_null_when_no_bid_meets_threshold(): void
    {
        $book = new OrderBook('BTCTMN', [
            new OrderBookLevel('100', '1'),
            new OrderBookLevel('99', '1'),
        ], []);

        $this->assertNull($book->firstBidWithMinNotional(15000000));
    }
}
