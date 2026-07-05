<?php

namespace Tests\Unit;

use App\Domain\Exchange\DTO\OrderBook;
use App\Domain\Exchange\DTO\OrderBookLevel;
use App\Domain\Trading\Services\OrderBookPricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderBookPricingServiceTest extends TestCase
{
    public function test_it_calculates_bid_average_until_usd_depth(): void
    {
        $book = new OrderBook('BTCTMN', [
            new OrderBookLevel('100', '10'),
            new OrderBookLevel('90', '20'),
        ], []);

        $average = (new OrderBookPricingService)->averagePriceOfDepth($book, 'bids', '200', 'TMN', '10');

        $this->assertSame('94.736842105263', $average);
    }

    public function test_it_calculates_bid_average_for_usdt_quote_without_tmn_conversion(): void
    {
        $book = new OrderBook('BTCUSDT', [
            new OrderBookLevel('100', '10'),
            new OrderBookLevel('90', '20'),
        ], []);

        $average = (new OrderBookPricingService)->averagePriceOfDepth($book, 'bids', '2000', 'USDT');

        $this->assertSame('94.736842105263', $average);
    }

    public function test_it_calculates_average_when_first_level_covers_depth(): void
    {
        $book = new OrderBook('BTCTMN', [], [
            new OrderBookLevel('1000000', '100'),
        ]);

        $average = (new OrderBookPricingService)->averagePriceOfDepth($book, 'asks', '200', 'TMN', '10');

        $this->assertSame('1000000.000000000000', $average);
    }

    public function test_it_calculates_usdt_tmn_price_from_bid_base_depth(): void
    {
        $book = new OrderBook('USDTTMN', [
            new OrderBookLevel('70000', '1000'),
            new OrderBookLevel('69000', '2000'),
        ], []);

        $average = (new OrderBookPricingService)->averagePriceOfDepth($book, 'bids', '2000', 'USDT', depthInBaseAsset: true);

        $this->assertSame('69500.000000000000', $average);
    }

    public function test_it_detects_large_blocking_bid_above_our_order_for_tmn_market(): void
    {
        $book = new OrderBook('BTCTMN', [
            new OrderBookLevel('105', '200000'),
            new OrderBookLevel('100', '1'),
        ], []);

        $this->assertTrue((new OrderBookPricingService)->hasAnyOrderAbove($book, '100', 'TMN', '15000000'));
    }

    public function test_it_detects_large_blocking_bid_above_our_order_for_usdt_market(): void
    {
        $book = new OrderBook('BTCUSDT', [
            new OrderBookLevel('50100', '10'),
            new OrderBookLevel('50000', '1'),
        ], []);

        $this->assertTrue((new OrderBookPricingService)->hasAnyOrderAbove($book, '50000', 'USDT', '500'));
    }

    #[DataProvider('nonBlockingBidCases')]
    public function test_it_ignores_non_blocking_bids(string $quoteAsset, array $bids, string $ourPrice, string $threshold): void
    {
        $book = new OrderBook('TEST', $bids, []);

        $this->assertFalse((new OrderBookPricingService)->hasAnyOrderAbove($book, $ourPrice, $quoteAsset, $threshold));
    }

    public static function nonBlockingBidCases(): array
    {
        return [
            'bid below our price with large notional' => [
                'TMN',
                [new OrderBookLevel('99', '200000')],
                '100',
                '15000000',
            ],
            'bid above our price with small notional' => [
                'TMN',
                [new OrderBookLevel('105', '1')],
                '100',
                '15000000',
            ],
            'bid above our price with small usdt notional' => [
                'USDT',
                [new OrderBookLevel('50100', '0.001')],
                '50000',
                '500',
            ],
        ];
    }
}
