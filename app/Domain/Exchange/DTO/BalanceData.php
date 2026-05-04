<?php

namespace App\Domain\Exchange\DTO;

final readonly class BalanceData
{
    public function __construct(
        public string $asset,
        public string $available,
        public string $locked = '0',
        public string $value = '0',
        public array $raw = [],
    ) {}
}
