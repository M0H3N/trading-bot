<?php

namespace App\Domain\Exchange\DTO;

final readonly class PlacedOrderData
{
    public function __construct(
        public string $clientId,
        public ?string $externalId,
        public string $status,
        public array $raw = [],
    ) {}
}
