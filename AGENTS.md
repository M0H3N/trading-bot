# Trading Bot — Agent Context

Laravel trading bot for Wallex (paper + live). Domain logic lives under `app/Domain/Trading/`.

## Stack

- PHP 8.3+, Laravel, Filament admin, queued jobs
- Exchange clients: `app/Infrastructure/Exchange/`

## Trading loops

1. **Entry (buy):** `EvaluateMarketJob` → `MarketEvaluationService` → `MonitorOrderJob` → `OrderMonitoringService`
2. **Exit (sell):** `ManageExitJob` → `ExitManagementService` (includes `monitorExitOrder`)

**Never route sell orders through `MonitorOrderJob`.** Entry and exit use different pricing logic.

## AI docs

Detailed domain docs: [`.ai/README.md`](.ai/README.md)

## Conventions

- Minimize scope; match existing service/job patterns
- Use `TradingOrder::entry()` / `exit()` scopes for side filtering
- Run tests with PHP 8.3: `php artisan test --filter=Trading`
