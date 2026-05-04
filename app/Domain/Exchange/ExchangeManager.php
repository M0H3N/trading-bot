<?php

namespace App\Domain\Exchange;

use App\Domain\Exchange\Contracts\ExchangeClient;
use App\Infrastructure\Exchange\Paper\PaperExchangeClient;
use App\Infrastructure\Exchange\Wallex\WallexClient;
use InvalidArgumentException;

class ExchangeManager
{
    public function __construct(private readonly WallexClient $wallexClient) {}

    public function client(?string $exchange = null, ?string $mode = null): ExchangeClient
    {
        $exchange ??= (string) config('trading.default_exchange', 'wallex');
        $mode ??= (string) config('trading.mode', 'paper');

        $client = match ($exchange) {
            'wallex' => $this->wallexClient,
            default => throw new InvalidArgumentException("Unsupported exchange [{$exchange}]."),
        };

        if ($mode === 'paper') {
            return new PaperExchangeClient($client);
        }

        return $client;
    }
}
