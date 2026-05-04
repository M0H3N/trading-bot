<?php

namespace App\Domain\Exchange\DTO;

final readonly class PlaceOrderData
{
    public function __construct(
        public string $symbol,
        public string $side,
        public string $type,
        public string $price,
        public string $amount,
        public string $clientId,
        public string $mode = 'paper',
    ) {}
}
