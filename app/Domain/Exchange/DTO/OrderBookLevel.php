<?php

namespace App\Domain\Exchange\DTO;

final readonly class OrderBookLevel
{
    public function __construct(
        public string $price,
        public string $amount,
    ) {}

    public function notional(): float
    {
        return (float) $this->price * (float) $this->amount;
    }
}
