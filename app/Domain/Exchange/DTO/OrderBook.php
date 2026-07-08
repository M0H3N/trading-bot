<?php

namespace App\Domain\Exchange\DTO;

final readonly class OrderBook
{
    /**
     * @param  array<int, OrderBookLevel>  $bids
     * @param  array<int, OrderBookLevel>  $asks
     */
    public function __construct(
        public string $symbol,
        public array $bids,
        public array $asks,
        public array $raw = [],
    ) {}

    public function topBid(): ?OrderBookLevel
    {
        return $this->bids[0] ?? null;
    }

    public function topAsk(): ?OrderBookLevel
    {
        return $this->asks[0] ?? null;
    }

    public function firstBidWithMinNotional(float $threshold): ?OrderBookLevel
    {
        foreach ($this->bids as $level) {
            if ($level->notional() >= $threshold) {
                return $level;
            }
        }

        return null;
    }

    public function firstAskWithMinNotional(float $threshold): ?OrderBookLevel
    {
        foreach ($this->asks as $level) {
            if ($level->notional() >= $threshold) {
                return $level;
            }
        }

        return null;
    }
}
