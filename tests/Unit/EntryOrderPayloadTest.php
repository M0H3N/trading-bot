<?php

namespace Tests\Unit;

use App\Domain\Trading\Services\EntryOrderPayload;
use PHPUnit\Framework\TestCase;

class EntryOrderPayloadTest extends TestCase
{
    public function test_average_price_from_wallex_fills(): void
    {
        $raw = [
            'result' => [
                'status' => 'FILLED',
                'executedQty' => '4553328.0000000000000000',
                'executedSum' => '3041623.1040000000000000',
                'executedPrice' => '0.668000000000000000',
                'fills' => [
                    [
                        'price' => '0.6680000000000000000',
                        'quantity' => '4553328.0000000000000000',
                    ],
                ],
            ],
        ];

        $this->assertSame('0.668', EntryOrderPayload::averagePrice($raw));
        $this->assertSame('4553328.0000000000000000', EntryOrderPayload::filledAmount($raw));
    }

    public function test_average_price_from_multiple_fills_when_executed_price_missing(): void
    {
        $raw = [
            'result' => [
                'executedQty' => '3',
                'fills' => [
                    ['price' => '10', 'quantity' => '1'],
                    ['price' => '20', 'quantity' => '2'],
                ],
            ],
        ];

        $this->assertSame('16.666666666667', EntryOrderPayload::averagePrice($raw));
    }

    public function test_average_price_from_executed_sum_and_qty(): void
    {
        $raw = [
            'result' => [
                'executedQty' => '2',
                'executedSum' => '30',
                'fills' => [],
            ],
        ];

        $this->assertSame('15', EntryOrderPayload::averagePrice($raw));
    }

    public function test_returns_null_when_exchange_payload_has_no_fill_data(): void
    {
        $raw = [
            'result' => [
                'status' => 'FILLED',
                'price' => '0.6680000000000000',
            ],
        ];

        $this->assertNull(EntryOrderPayload::averagePrice($raw));
        $this->assertNull(EntryOrderPayload::filledAmount($raw));
    }
}
