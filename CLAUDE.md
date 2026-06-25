# Trading Bot

@.ai/README.md

## Critical rule: buy vs sell monitoring

- `MonitorOrderJob` + `OrderMonitoringService` → **buy only**
- `ManageExitJob` + `ExitManagementService` → **sell only**
- Dispatch uses `monitorable()->entry()` — see `.ai/trading/order-monitoring.md`

## Key paths

- Services: `app/Domain/Trading/Services/`
- Jobs: `app/Jobs/Trading/`
- Dispatch: `app/Console/Commands/DispatchTradingJobs.php`
- Schedule: `routes/console.php`

## Tests

```bash
php artisan test --filter=ImmediateEntryFill
php artisan test --filter=PaperTradingFlow
php artisan test --filter=TradingServiceToggles
```

PHP 8.3+ required.
