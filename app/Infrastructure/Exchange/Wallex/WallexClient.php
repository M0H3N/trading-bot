<?php

namespace App\Infrastructure\Exchange\Wallex;

use App\Domain\Exchange\Contracts\ExchangeClient;
use App\Domain\Exchange\DTO\BalanceData;
use App\Domain\Exchange\DTO\FairPriceData;
use App\Domain\Exchange\DTO\OrderBook;
use App\Domain\Exchange\DTO\OrderBookLevel;
use App\Domain\Exchange\DTO\OrderStatusData;
use App\Domain\Exchange\DTO\PlacedOrderData;
use App\Domain\Exchange\DTO\PlaceOrderData;
use App\Domain\Http\Services\HttpLogService;
use App\Domain\Trading\Services\CircuitBreakerService;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException as ClientRequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WallexClient implements ExchangeClient
{
    public function __construct(
        private readonly CircuitBreakerService $circuitBreaker,
        private readonly HttpLogService $httpLogs,
    ) {}

    public function name(): string
    {
        return 'wallex';
    }

    public function getFairPrice(string $symbol): FairPriceData
    {
        return $this->circuitBreaker->guard('wallex', 'market-data', function () use ($symbol): FairPriceData {
            $payload = $this->send('market-data', 'GET', '/all-fairPrice')->throw()->json();
            $price = $this->extractFairPrice($payload, $symbol);

            if ($price === null) {
                throw new RuntimeException("Fair price for [{$symbol}] was not found in Wallex response.");
            }

            return new FairPriceData($symbol, (string) $price, $payload ?? []);
        });
    }

    public function getOrderBook(string $symbol): OrderBook
    {
        return $this->circuitBreaker->guard('wallex', 'market-data', function () use ($symbol): OrderBook {
            $payload = $this->send('market-data', 'GET', '/depth', ['query' => ['symbol' => $symbol]])->throw()->json();
            $book = Arr::get($payload, 'result', $payload ?? []);

            return new OrderBook(
                symbol: $symbol,
                bids: $this->mapLevels(Arr::get($book, 'bid', Arr::get($book, 'bids', []))),
                asks: $this->mapLevels(Arr::get($book, 'ask', Arr::get($book, 'asks', []))),
                raw: $payload ?? [],
            );
        });
    }

    public function placeOrder(PlaceOrderData $order): PlacedOrderData
    {
        return $this->circuitBreaker->guard('wallex', 'orders', function () use ($order): PlacedOrderData {
            $payload = $this->send('orders', 'POST', '/account/orders', [
                'json' => [
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'type' => $order->type,
                    'price' => $order->price,
                    'quantity' => $order->amount,
                    'client_id' => $order->clientId,
                ],
            ])->throw()->json();

            $result = Arr::get($payload, 'result', $payload ?? []);

            return new PlacedOrderData(
                clientId: $order->clientId,
                externalId: (string) (Arr::get($result, 'id') ?? Arr::get($result, 'order_id') ?? ''),
                status: $this->normalizeStatus((string) (Arr::get($result, 'status') ?? 'open')),
                raw: $payload ?? [],
            );
        });
    }

    public function cancelOrder(string $clientId): void
    {
        $this->circuitBreaker->guard('wallex', 'orders', function () use ($clientId): void {
            $this->send('orders', 'DELETE', "/account/orders/{$clientId}")->throw();
        });
    }

    public function getOrderStatus(string $clientId): OrderStatusData
    {
        return $this->circuitBreaker->guard('wallex', 'orders', function () use ($clientId): OrderStatusData {
            $payload = $this->send('orders', 'GET', "/account/orders/{$clientId}")->throw()->json();
            $result = Arr::get($payload, 'result', $payload ?? []);

            return new OrderStatusData(
                clientId: $clientId,
                status: $this->normalizeStatus((string) (Arr::get($result, 'status'))),
                filledAmount: (string) (Arr::get($result, 'executedQty')),
                averagePrice: Arr::get($result, 'averagePrice') ? (string) Arr::get($result, 'averagePrice') : null,
                raw: $payload ?? [],
            );
        });
    }

    public function getBalance(string $asset): BalanceData
    {
        return $this->circuitBreaker->guard('wallex', 'balances', function () use ($asset): BalanceData {
            $payload = $this->send('balances', 'GET', '/account/balances')->throw()->json();
            $row = $this->findBalanceRow($payload, $asset);

            return new BalanceData(
                asset: $asset,
                available: (string) ($row['value'] - $row['locked']),
                locked: (string) ($row['locked'] ??  0),
                value: (string) ($row['value'] ??  0),
                raw: $payload ?? [],
            );
        });
    }

    /**
     * Wallex returns balances as result.balances[SYMBOL] => { asset, value, locked, ... }.
     */
    protected function findBalanceRow(array $payload, string $asset): array
    {
        $root = Arr::get($payload, 'result', Arr::get($payload, 'data', $payload));
        $map = is_array($root) ? Arr::get($root, 'balances', $root) : [];

        if (! is_array($map)) {
            return [];
        }

        $key = strtoupper($asset);
        if (isset($map[$asset]) && is_array($map[$asset])) {
            return $map[$asset];
        }
        if (isset($map[$key]) && is_array($map[$key])) {
            return $map[$key];
        }

        foreach ($map as $sym => $row) {
            if (! is_array($row)) {
                continue;
            }
            $rowAsset = strtoupper((string) ($row['asset'] ?? $row['symbol'] ?? $sym));
            if ($rowAsset === $key) {
                return $row;
            }
        }

        return [];
    }

    protected function send(string $scope, string $method, string $url, array $options = [], bool $absoluteUrl = false): Response
    {
        $startedAt = microtime(true);
        $requestBody = $options['json'] ?? $options['query'] ?? null;
        $fullUrl = $absoluteUrl ? $url : rtrim((string) config('trading.exchanges.wallex.base_url'), '/').'/'.ltrim($url, '/');

        try {
            $response = ($absoluteUrl ? $this->httpWithoutBaseUrl() : $this->http())->send($method, $url, $options);
            $this->recordHttpLog($scope, $method, $fullUrl, $requestBody, $response, $startedAt);

            return $response;
        } catch (Throwable $exception) {
            $errorResponse = $this->responseFromException($exception);
            $this->recordHttpLog($scope, $method, $fullUrl, $requestBody, $errorResponse, $startedAt, $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Resolve an HTTP client response from a thrown exception (e.g. 4xx/5xx wrapped by Laravel).
     */
    protected function responseFromException(Throwable $exception): ?Response
    {
        $e = $exception;
        for ($i = 0; $i < 6 && $e !== null; $i++) {
            if ($e instanceof ClientRequestException) {
                return $e->response;
            }
            if ($e instanceof GuzzleRequestException && $e->hasResponse()) {
                return new Response($e->getResponse());
            }
            $e = $e->getPrevious();
        }

        return null;
    }

    protected function recordHttpLog(
        string $scope,
        string $method,
        string $url,
        mixed $requestBody,
        ?Response $response,
        float $startedAt,
        ?string $error = null,
    ): void {
        $this->httpLogs->record(
            exchange: 'wallex',
            scope: $scope,
            method: $method,
            url: $url,
            requestHeaders: $this->requestHeaders(),
            requestBody: $requestBody,
            statusCode: $response?->status(),
            responseHeaders: $response?->headers() ?? [],
            responseBody: $this->responseBody($response),
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $error,
        );
    }

    protected function http(): PendingRequest
    {
        return $this->httpWithoutBaseUrl()
            ->baseUrl((string) config('trading.exchanges.wallex.base_url'));
    }

    protected function httpWithoutBaseUrl(): PendingRequest
    {
        return Http::withHeaders($this->requestHeaders())
            ->acceptJson()
            ->timeout((int) config('trading.exchanges.wallex.timeout'))
            ->retry((int) config('trading.exchanges.wallex.retry_times'), (int) config('trading.exchanges.wallex.retry_sleep_ms'));
    }

    protected function requestHeaders(): array
    {
        return [
            'x-api-key' => (string) config('trading.exchanges.wallex.api_key'),
            'Accept' => 'application/json',
        ];
    }

    protected function responseBody(?Response $response): mixed
    {
        if (! $response) {
            return null;
        }

        $stream = $response->getBody();
        $raw = (string) $stream;
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }

        // JSON literal `null` becomes PHP null — keep raw text for the audit column
        if ($decoded === null) {
            return $raw;
        }

        return $decoded;
    }

    protected function extractFairPrice(?array $payload, string $symbol): mixed
    {
        $result = Arr::get($payload, 'result', Arr::get($payload, 'data', $payload ?? []));
        $value = Arr::get($result, $symbol);

        if (is_array($value)) {
            return $value['price'] ?? $value['fairPrice'] ?? $value['value'] ?? null;
        }

        return $value;
    }

    protected function mapLevels(array $levels): array
    {
        return collect($levels)
            ->map(function (array $level): OrderBookLevel {
                return new OrderBookLevel(
                    price: (string) ($level['price'] ?? $level[0] ?? 0),
                    amount: (string) ($level['quantity'] ?? $level['amount'] ?? $level[1] ?? 0),
                );
            })
            ->filter(fn (OrderBookLevel $level): bool => (float) $level->price > 0 && (float) $level->amount > 0)
            ->values()
            ->all();
    }

    protected function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'FILLED', => 'filled',
            'CANCELED' => 'cancelled',
            'PARTIALLY_FILLED' => 'partially_filled',
            'NEW', => 'open',
            default => 'open',
        };
    }
}
