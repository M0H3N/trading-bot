# Jobs & Schedule

## Artisan dispatch

```bash
php artisan trading:dispatch --scope=all      # همه
php artisan trading:dispatch --scope=evaluate   # فقط EvaluateMarketJob
php artisan trading:dispatch --scope=monitor    # فقط MonitorOrderJob (buy)
php artisan trading:dispatch --scope=exit     # فقط ManageExitJob (sell)
```

## Schedule (`routes/console.php`)

| Command | Interval | Job |
|---------|----------|-----|
| `trading:dispatch --scope=evaluate` | هر ۱ دقیقه | `EvaluateMarketJob` per active market |
| `trading:dispatch --scope=monitor` | هر ۱۰ ثانیه | `MonitorOrderJob` per `monitorable()->entry()` |
| `trading:dispatch --scope=exit` | هر ۳۰ ثانیه | `ManageExitJob` per open deal |
| `markets:sync` | هر ۱ دقیقه | sync بازارها |

## Feature toggles

| Setting | Job affected |
|---------|--------------|
| `market_evaluation_enabled` | `EvaluateMarketJob` |
| `exit_management_enabled` | `ManageExitJob` |
| monitor scope | همیشه dispatch می‌شود (toggle ندارد) |

## Queue

هر نوع job صف جداگانه دارد (`config/trading.queues`):

| Queue | Job | توضیح |
|-------|-----|-------|
| `trading-evaluate` | `EvaluateMarketJob` | ارزیابی بازار / entry |
| `trading-monitor` | `MonitorOrderJob` | مانیتور buy |
| `trading-exit` | `ManageExitJob` | exit همه dealها — ترتیب per asset با `WithoutOverlapping` |
| `trading-maintenance` | Cancel*, Expire* | عملیات نگهداری |

`ManageExitJob` middleware `WithoutOverlapping` روی `trading:exit:{exchange}:{ASSET}` دارد؛ dealهای PEPETMN پشت‌سرهم اجرا می‌شوند، TRX همزمان با PEPE — بدون نیاز به صف جدا per market.

### Worker

```bash
php artisan trading:queue-work
```

همین. یک worker همه صف‌های trading را با اولویت `monitor → exit → evaluate → maintenance` پردازش می‌کند.

یا دستی:

```bash
php artisan queue:work redis --queue=trading-monitor,trading-exit,trading-evaluate,trading-maintenance
```

Env: `TRADING_QUEUE_EVALUATE`, `TRADING_QUEUE_MONITOR`, `TRADING_QUEUE_EXIT`, `TRADING_QUEUE_MAINTENANCE`

- Retry: 3 tries، backoff 10s
- Lock: `Cache::lock` با TTL از `config('trading.lock_ttl')`

## سایر Jobها

| Job | Trigger |
|-----|---------|
| `ExpireOpeningDealsJob` | غیرفعال شدن `market_evaluation_enabled` (Filament) یا cancel entry در `OrderMonitoringService` |
| `CancelDealExitOrdersJob` | manual / Filament action |
