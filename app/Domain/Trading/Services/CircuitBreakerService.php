<?php

namespace App\Domain\Trading\Services;

use App\Models\CircuitBreaker;
use Closure;
use RuntimeException;
use Throwable;

class CircuitBreakerService
{
    public function guard(string $exchange, string $scope, Closure $callback): mixed
    {
        $breaker = CircuitBreaker::query()->firstOrCreate([
            'exchange' => $exchange,
            'scope' => $scope,
        ]);

        if ($breaker->opened_until && $breaker->opened_until->isFuture()) {
            throw new RuntimeException("Circuit breaker is open for [{$exchange}:{$scope}] until {$breaker->opened_until->toIso8601String()}.");
        }

        try {
            $result = $callback();
            $breaker->forceFill(['failure_count' => 0, 'opened_until' => null, 'last_error' => null])->save();

            return $result;
        } catch (Throwable $exception) {
            $failureCount = $breaker->failure_count + 1;
            $openedUntil = $failureCount >= (int) config('trading.circuit_breaker.failure_threshold')
                ? now()->addSeconds((int) config('trading.circuit_breaker.cooldown_seconds'))
                : null;

            $breaker->forceFill([
                'failure_count' => $failureCount,
                'opened_until' => $openedUntil,
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}
