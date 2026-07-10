<?php

namespace App\Domain\Exchange\Services;

use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class ExchangeApiRateLimiterService
{
    /**
     * Wait for a rate-limit slot, then reserve it before the HTTP request is sent.
     */
    public function acquire(string $exchange, string $method, string $path): void
    {
        if (! config('trading.rate_limits.enabled', true)) {
            return;
        }

        $endpointKey = $this->endpointKey($method, $path);
        $limits = $this->limitsFor($endpointKey);

        if ($limits === null) {
            return;
        }

        $limiterKey = $this->limiterKey($exchange, $endpointKey);
        $maxAttempts = (int) $limits['max_attempts'];
        $decaySeconds = (int) $limits['decay_seconds'];
        $maxWaitSeconds = (int) config('trading.rate_limits.max_wait_seconds', 120);
        $waitedSeconds = 0;

        while (true) {
            $acquired = RateLimiter::attempt(
                $limiterKey,
                $maxAttempts,
                fn (): bool => true,
                $decaySeconds,
            );

            if ($acquired !== false) {
                return;
            }

            $availableIn = RateLimiter::availableIn($limiterKey);

            if ($waitedSeconds + $availableIn > $maxWaitSeconds) {
                throw new RuntimeException(
                    "Exchange API rate limit exceeded for [{$exchange}:{$endpointKey}] after waiting {$waitedSeconds}s."
                );
            }

            sleep(max(1, min($availableIn, $maxWaitSeconds - $waitedSeconds)));
            $waitedSeconds += $availableIn;
        }
    }

    public function endpointKey(string $method, string $path): string
    {
        $normalizedPath = $this->normalizePath($path);

        return strtoupper($method).' '.$normalizedPath;
    }

    protected function limiterKey(string $exchange, string $endpointKey): string
    {
        return 'exchange-api:'.$exchange.':'.$endpointKey;
    }

    /**
     * @return array{max_attempts: int, decay_seconds: int}|null
     */
    protected function limitsFor(string $endpointKey): ?array
    {
        $endpoints = config('trading.rate_limits.endpoints', []);
        $limits = $endpoints[$endpointKey] ?? $endpoints['default'] ?? null;

        if (! is_array($limits)) {
            return null;
        }

        if (! isset($limits['max_attempts'], $limits['decay_seconds'])) {
            return null;
        }

        return [
            'max_attempts' => (int) $limits['max_attempts'],
            'decay_seconds' => (int) $limits['decay_seconds'],
        ];
    }

    protected function normalizePath(string $path): string
    {
        $path = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');

        if (preg_match('#^/account/orders/[^/]+$#', $path) === 1) {
            return '/account/orders/{id}';
        }

        return $path;
    }
}
