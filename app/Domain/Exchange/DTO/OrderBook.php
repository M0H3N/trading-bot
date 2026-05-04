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
}
