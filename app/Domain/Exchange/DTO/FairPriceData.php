<?php

namespace App\Domain\Exchange\DTO;

final readonly class FairPriceData
{
    public function __construct(
        public string $symbol,
        public string $price,
        public array $raw = [],
    ) {}
}
