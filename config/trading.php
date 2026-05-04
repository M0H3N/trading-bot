<?php

return [
    'enabled' => env('TRADING_ENABLED', true),
    'mode' => env('TRADING_MODE', 'paper'),
    'default_exchange' => env('TRADING_DEFAULT_EXCHANGE', 'wallex'),
    'queue' => env('TRADING_QUEUE', 'default'),
    'lock_ttl' => (int) env('TRADING_LOCK_TTL', 30),
    'monitor_interval' => (int) env('TRADING_MONITOR_INTERVAL', 5),
    'exit_interval' => (int) env('TRADING_EXIT_INTERVAL', 30),

    'settings' => [
        'bot_enabled' => false,
        'trading_mode' => 'paper',
        'entry_threshold_percent' => '0.10',
        'initial_exit_percent' => '0.10',
        'exit_step_percent' => '0.01',
        'exit_top_ask_from_percent' => '0.07',
        'minimum_exit_percent' => '0.02',
        'stop_loss_percent' => '1.00',
        'trade_balance_percent' => '25.00',
        'blocker_threshold_tmn' => '15000000',
        'tick_offset' => '4',
        'depth_usd' => '2000',
    ],

    'paper' => [
        'default_quote_balance' => env('TRADING_PAPER_QUOTE_BALANCE', '1000000000'),
    ],

    'http_logging' => [
        'enabled' => env('TRADING_HTTP_LOGGING_ENABLED', true),
        /** When set, HTTP logs use this connection (must exist in config/database.php). */
        'database_connection' => env('TRADING_HTTP_LOG_DB_CONNECTION'),
        'redact_headers' => [
            'authorization',
            'x-api-key',
            'cookie',
            'set-cookie',
        ],
    ],

    'exchanges' => [
        'wallex' => [
            'base_url' => env('WALLEX_BASE_URL', 'https://api.wallex.ir/v1'),
            'api_key' => env('WALLEX_API_KEY','18601|3rk4zqe4QK7aa2M3EOc6R5eYofD2w75Ua6LUrd6p'),
            'timeout' => (int) env('WALLEX_TIMEOUT', 10),
            'retry_times' => (int) env('WALLEX_RETRY_TIMES', 3),
            'retry_sleep_ms' => (int) env('WALLEX_RETRY_SLEEP_MS', 250),
        ],
    ],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('TRADING_CIRCUIT_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('TRADING_CIRCUIT_COOLDOWN_SECONDS', 300),
    ],
];
