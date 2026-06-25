# Order Monitoring: Buy vs Sell

## خلاصه

`MonitorOrderJob` **فقط** اردرهای **buy (entry)** را مانیتور می‌کند.

اوردرهای **sell (exit)** توسط `ManageExitJob` → `ExitManagementService::monitorExitOrder()` مدیریت می‌شوند.

## چرا تفکیک لازم است؟

`OrderMonitoringService` منطق entry دارد:

- مقایسه عمق **asks** با fair price
- `entry_threshold_percent` — آیا فرصت arbitrage هنوز هست؟
- `blocker_threshold_tmn` — آیا اردر بزرگ‌تری جلوی fill ماست؟
- در صورت cancel، `MarketEvaluationService::evaluate()` برای buy جدید

این منطق برای sell معنا ندارد. sell به `exit_percent`، `stop_loss_percent`، و `topAsk` وابسته است (`ExitManagementService`).

## باگ و فیکس (2026-06)

### مشکل

`scopeMonitorable()` همه اردرهای `active()` را برمی‌گرداند — **بدون فیلتر side**. در نتیجه اردر sell فعال هم به `MonitorOrderJob` می‌رفت و با منطق entry اشتباه cancel می‌شد، در حالی که `ManageExitJob` همان اردر را جداگانه مدیریت می‌کرد.

### فیکس اعمال‌شده

در `DispatchTradingJobs` فیلتر `->entry()` اضافه شد:

```php
TradingOrder::query()->monitorable()->entry()->pluck('id')->each(
    fn (int $id) => MonitorOrderJob::dispatch($id)
);
```

### بهبود پیشنهادی (اختیاری)

برای defense-in-depth، `scopeMonitorable()` را هم می‌توان به buy محدود کرد:

```php
return $query->entry()->where(function (Builder $query): void {
    $query->active()
        ->orWhere(function (Builder $query): void {
            $query->where('status', 'filled')
                ->whereDoesntHave('trades', fn ($t) => $t->where('side', 'buy'));
        });
});
```

## مسیر dispatch

| Job | Query | سرویس | ساید |
|-----|-------|--------|------|
| `MonitorOrderJob` | `monitorable()->entry()` | `OrderMonitoringService` | buy |
| `ManageExitJob` | `Deal::open()` | `ExitManagementService` | sell |

## `monitorable()` — دو حالت

1. **Active orders** — polling وضعیت از صرافی
2. **Filled buy بدون trade** — ثبت fill که از دست رفته (مثلاً immediate fill)

> **نکته:** `OrderMonitoringService::monitor()` در خط ۲۹ برای `status = filled` زود return می‌کند. اگر حالت دوم باید کار کند، سرویس هم باید اصلاح شود. تست: `ImmediateEntryFillTest`.

## چک‌لیست قبل از تغییر

- [ ] آیا تغییر روی buy است یا sell؟ مسیر job درست انتخاب شده؟
- [ ] آیا `monitorable()` بدون `entry()` dispatch می‌شود؟
- [ ] آیا منطق entry (asks، `entry_threshold_percent`) روی sell اعمال نشده؟
- [ ] تست‌های `ImmediateEntryFillTest` و `PaperTradingFlowTest` اجرا شده؟
