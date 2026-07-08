# Order Monitoring: Entry Leg vs Exit Leg

## خلاصه

`MonitorOrderJob` **فقط** leg **ورود** deal را مانیتور می‌کند:

| direction | entry leg (monitor) | exit leg (ManageExitJob) |
|-----------|---------------------|---------------------------|
| `long` | buy | sell |
| `short` | sell | buy |

## چرا تفکیک لازم است؟

`OrderMonitoringService` منطق **ورود** دارد (فرصت arbitrage، blocker، cancel و re-evaluate).

leg **خروج** به `exit_percent`، `stop_loss_percent`، repricing وابسته است (`ExitManagementService`).

## Dispatch

```php
TradingOrder::query()->monitorable()->entryLeg()->pluck('id')->each(
    fn (int $id) => MonitorOrderJob::dispatch($id)
);
```

`entryLeg()` = buy برای long، sell برای short (join با `deals.direction`).

## مسیر dispatch

| Job | Query | سرویس | Leg |
|-----|-------|--------|-----|
| `MonitorOrderJob` | `monitorable()->entryLeg()` | `OrderMonitoringService` | entry |
| `ManageExitJob` | `Deal::open()` | `ExitManagementService` | exit |

در سرویس‌ها از `Deal::entrySide()` / `exitSide()` استفاده کنید، نه hardcode buy/sell.

## `monitorable()` — دو حالت

1. **Active orders** — polling وضعیت از صرافی
2. **Filled entry-leg بدون trade** — ثبت fill از دست‌رفته

## چک‌لیست قبل از تغییر

- [ ] آیا تغییر روی entry leg است یا exit leg؟
- [ ] آیا `monitorable()` بدون `entryLeg()` dispatch می‌شود؟
- [ ] آیا منطق entry روی exit leg اعمال نشده؟
- [ ] تست‌های `ImmediateEntryFillTest`، `ShortTradingFlowTest`، `PaperTradingFlowTest` اجرا شده؟
