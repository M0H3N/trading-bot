# Trading Bot

@.ai/README.md

## Critical rule: entry leg vs exit leg monitoring

- `MonitorOrderJob` + `OrderMonitoringService` → **entry leg only** (buy for long, sell for short)
- `ManageExitJob` + `ExitManagementService` → **exit leg** (sell for long, buy for short)
- Dispatch uses `monitorable()->entryLeg()` — see `.ai/trading/order-monitoring.md`

Deal `direction`: `long` (buy→sell) | `short` (sell→buy). Per-market: `long_enabled`, `short_enabled`.

## Key paths

- Services: `app/Domain/Trading/Services/`
- Jobs: `app/Jobs/Trading/`
- Dispatch: `app/Console/Commands/DispatchTradingJobs.php`
- Schedule: `routes/console.php`

## Tests

```bash
php artisan test --filter=ImmediateEntryFill
php artisan test --filter=PaperTradingFlow
php artisan test --filter=ShortTrading
php artisan test --filter=TradingServiceToggles
```

PHP 8.3+ required.
