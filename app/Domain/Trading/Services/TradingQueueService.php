<?php

namespace App\Domain\Trading\Services;

final class TradingQueueService
{
    public static function evaluate(): string
    {
        return (string) config('trading.queues.evaluate');
    }

    public static function monitor(): string
    {
        return (string) config('trading.queues.monitor');
    }

    public static function exit(): string
    {
        return (string) config('trading.queues.exit');
    }

    public static function maintenance(): string
    {
        return (string) config('trading.queues.maintenance');
    }

    /**
     * Queues for `php artisan trading:queue-work` (priority order).
     *
     * @return list<string>
     */
    public static function workerQueues(): array
    {
        return [
            self::monitor(),
            self::exit(),
            self::evaluate(),
            self::maintenance(),
        ];
    }
}
