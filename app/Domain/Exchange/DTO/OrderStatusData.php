<?php

namespace App\Domain\Exchange\DTO;

final readonly class OrderStatusData
{
    public function __construct(
        public string $clientId,
        public string $status,
        public ?string $filledAmount = null,
        public ?string $averagePrice = null,
        public array $raw = [],
    ) {}

    public function isFilled(): bool
    {
        return $this->status === 'filled';
    }
}

