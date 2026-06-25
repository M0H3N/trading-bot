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
| `trading:expire-opening-deals` | هر ۱ دقیقه | انقضای dealهای opening |
| `markets:sync` | هر ۱ دقیقه | sync بازارها |

## Feature toggles

| Setting | Job affected |
|---------|--------------|
| `market_evaluation_enabled` | `EvaluateMarketJob` |
| `exit_management_enabled` | `ManageExitJob` |
| monitor scope | همیشه dispatch می‌شود (toggle ندارد) |

## Queue

- Queue name: `config('trading.queue')` — پیش‌فرض `default`
- Retry: 3 tries، backoff 10s
- Lock: `Cache::lock` با TTL از `config('trading.lock_ttl')`

## سایر Jobها

| Job | Trigger |
|-----|---------|
| `ExpireOpeningDealsJob` | schedule |
| `CancelDealExitOrdersJob` | manual / Filament action |
