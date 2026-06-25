---
name: trading-order-monitoring
description: >-
  Guides changes to entry order monitoring (MonitorOrderJob, OrderMonitoringService)
  and enforces buy-only scope vs ManageExitJob for sells. Use when working on
  order monitoring, monitorable scope, DispatchTradingJobs monitor scope, or
  buy/sell job separation.
---

# Trading Order Monitoring

## When to use

- Editing `MonitorOrderJob`, `OrderMonitoringService`, `scopeMonitorable`
- Debugging why sell orders were cancelled incorrectly
- Adding dispatch or polling for open orders

## Architecture

| Path | Side | Responsibility |
|------|------|----------------|
| `MonitorOrderJob` → `OrderMonitoringService` | buy | Status poll, fill recording, cancel if entry opportunity gone |
| `ManageExitJob` → `ExitManagementService` | sell | Repricing, stop-loss, `monitorExitOrder` |

## Dispatch (current)

```php
TradingOrder::query()->monitorable()->entry()->pluck('id')->each(
    fn (int $id) => MonitorOrderJob::dispatch($id)
);
```

## Before merging

1. Confirm no sell path uses `OrderMonitoringService`
2. If changing `monitorable()`, verify dispatch still filters `entry()`
3. Run `php artisan test --filter=ImmediateEntryFill` (PHP 8.3+)

## Reference

- `.ai/trading/order-monitoring.md`
- `.ai/trading/jobs-and-schedule.md`
