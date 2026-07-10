<?php

namespace Tests\Unit;

use App\Domain\Exchange\Services\ExchangeApiRateLimiterService;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class ExchangeApiRateLimiterServiceTest extends TestCase
{
    private ExchangeApiRateLimiterService $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = app(ExchangeApiRateLimiterService::class);
    }

    public function test_endpoint_key_normalizes_order_paths_with_dynamic_ids(): void
    {
        $this->assertSame(
            'GET /account/orders/{id}',
            $this->rateLimiter->endpointKey('GET', '/account/orders/my-client-id-123'),
        );

        $this->assertSame(
            'DELETE /account/orders/{id}',
            $this->rateLimiter->endpointKey('DELETE', '/account/orders/my-client-id-123'),
        );
    }

    public function test_acquire_reserves_slot_under_limit(): void
    {
        config([
            'trading.rate_limits.enabled' => true,
            'trading.rate_limits.max_wait_seconds' => 5,
            'trading.rate_limits.endpoints' => [
                'GET /all-fairPrice' => [
                    'max_attempts' => 2,
                    'decay_seconds' => 60,
                ],
            ],
        ]);

        RateLimiter::clear('exchange-api:wallex:GET /all-fairPrice');

        $this->rateLimiter->acquire('wallex', 'GET', '/all-fairPrice');
        $this->rateLimiter->acquire('wallex', 'GET', '/all-fairPrice');

        $this->assertTrue(RateLimiter::tooManyAttempts('exchange-api:wallex:GET /all-fairPrice', 2));
    }

    public function test_endpoints_have_independent_rate_limit_keys(): void
    {
        config([
            'trading.rate_limits.enabled' => true,
            'trading.rate_limits.max_wait_seconds' => 5,
            'trading.rate_limits.endpoints' => [
                'GET /all-fairPrice' => [
                    'max_attempts' => 1,
                    'decay_seconds' => 60,
                ],
                'GET /depth' => [
                    'max_attempts' => 1,
                    'decay_seconds' => 60,
                ],
            ],
        ]);

        RateLimiter::clear('exchange-api:wallex:GET /all-fairPrice');
        RateLimiter::clear('exchange-api:wallex:GET /depth');

        $this->rateLimiter->acquire('wallex', 'GET', '/all-fairPrice');
        $this->rateLimiter->acquire('wallex', 'GET', '/depth');

        $this->assertTrue(RateLimiter::tooManyAttempts('exchange-api:wallex:GET /all-fairPrice', 1));
        $this->assertTrue(RateLimiter::tooManyAttempts('exchange-api:wallex:GET /depth', 1));
    }

    public function test_acquire_throws_when_wait_budget_is_exceeded(): void
    {
        config([
            'trading.rate_limits.enabled' => true,
            'trading.rate_limits.max_wait_seconds' => 1,
            'trading.rate_limits.endpoints' => [
                'POST /account/orders' => [
                    'max_attempts' => 1,
                    'decay_seconds' => 30,
                ],
            ],
        ]);

        RateLimiter::clear('exchange-api:wallex:POST /account/orders');

        $this->rateLimiter->acquire('wallex', 'POST', '/account/orders');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Exchange API rate limit exceeded for [wallex:POST /account/orders]');

        $this->rateLimiter->acquire('wallex', 'POST', '/account/orders');
    }

    public function test_acquire_is_skipped_when_rate_limiting_is_disabled(): void
    {
        config([
            'trading.rate_limits.enabled' => false,
            'trading.rate_limits.endpoints' => [
                'GET /all-fairPrice' => [
                    'max_attempts' => 1,
                    'decay_seconds' => 60,
                ],
            ],
        ]);

        RateLimiter::clear('exchange-api:wallex:GET /all-fairPrice');

        $this->rateLimiter->acquire('wallex', 'GET', '/all-fairPrice');
        $this->rateLimiter->acquire('wallex', 'GET', '/all-fairPrice');

        $this->assertFalse(RateLimiter::tooManyAttempts('exchange-api:wallex:GET /all-fairPrice', 1));
    }
}
