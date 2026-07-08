# Trading Bot — Agent Context

Laravel trading bot for Wallex (paper + live). Domain logic lives under `app/Domain/Trading/`.

## Stack

- PHP 8.3+, Laravel, Filament admin, queued jobs
- Exchange clients: `app/Infrastructure/Exchange/`

## Trading loops

Deals have `direction`: **long** (buy → sell) or **short** (sell → buy). Per-market toggles: `long_enabled`, `short_enabled`.

1. **Entry leg:** `EvaluateMarketJob` → `MarketEvaluationService` → `MonitorOrderJob` → `OrderMonitoringService`
2. **Exit leg:** `ManageExitJob` → `ExitManagementService` (includes `monitorExitOrder`)

**Never route exit-leg orders through `MonitorOrderJob`.** Use `Deal::entrySide()` / `exitSide()` in deal context; dispatch monitor with `monitorable()->entryLeg()`.

## AI docs

Detailed domain docs: [`.ai/README.md`](.ai/README.md)

## Conventions

- Minimize scope; match existing service/job patterns
- `TradingOrder::entry()` / `exit()` = raw exchange side; `entryLeg()` = first deal leg
- Run tests with PHP 8.3: `php artisan test --filter=Trading`
