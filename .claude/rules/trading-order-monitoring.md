---
paths:
  - app/Domain/Trading/Services/OrderMonitoringService.php
  - app/Jobs/Trading/MonitorOrderJob.php
  - app/Console/Commands/DispatchTradingJobs.php
  - app/Models/TradingOrder.php
---

# Order Monitoring (Entry Leg Only)

Full doc: `.ai/trading/order-monitoring.md`

## Rules

1. `MonitorOrderJob` dispatches only for `monitorable()->entryLeg()` orders.
2. Exit-leg monitoring belongs in `ExitManagementService::monitorExitOrder()`, triggered by `ManageExitJob`.
3. `scopeMonitorable()` may include non-entry-leg active orders — always filter `->entryLeg()` at dispatch.
4. `OrderMonitoringService` cancels unfilled entry legs when opportunity is gone or blocked; then re-evaluates the market.

## Anti-patterns

- Dispatching `MonitorOrderJob` for exit-leg orders
- Adding exit repricing / stop-loss logic to `OrderMonitoringService`
- Hardcoding buy/sell in deal context instead of `Deal::entrySide()` / `exitSide()`
