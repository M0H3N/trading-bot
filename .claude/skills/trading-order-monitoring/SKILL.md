---
name: trading-order-monitoring
description: >-
  Guides changes to entry-leg order monitoring (MonitorOrderJob, OrderMonitoringService)
  vs ManageExitJob for exit legs. Use when working on order monitoring, monitorable scope,
  DispatchTradingJobs monitor scope, or long/short deal separation.
---

# Trading Order Monitoring

## When to use

- Editing `MonitorOrderJob`, `OrderMonitoringService`, `scopeMonitorable`, `scopeEntryLeg`
- Debugging incorrect cancel on entry or exit orders
- Adding dispatch or polling for open orders

## Architecture

| Path | Leg | Responsibility |
|------|-----|----------------|
| `MonitorOrderJob` → `OrderMonitoringService` | entry (buy long / sell short) | Status poll, fill recording, cancel if entry opportunity gone |
| `ManageExitJob` → `ExitManagementService` | exit (sell long / buy short) | Repricing, stop-loss, `monitorExitOrder` |

## Dispatch (current)

```php
TradingOrder::query()->monitorable()->entryLeg()->pluck('id')->each(
    fn (int $id) => MonitorOrderJob::dispatch($id)
);
```

## Before merging

1. Confirm no exit-leg path uses `OrderMonitoringService`
2. If changing `monitorable()`, verify dispatch still filters `entryLeg()`
3. Run `php artisan test --filter=ImmediateEntryFill` and `--filter=ShortTrading` (PHP 8.3+)

## Reference

- `.ai/trading/order-monitoring.md`
- `.ai/trading/jobs-and-schedule.md`
