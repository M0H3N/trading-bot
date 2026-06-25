# AI Documentation Hub

مرجع داخلی پروژه برای Cursor، Claude Code و سایر agentها. قبل از تغییر در منطق تریدینگ، این پوشه را بخوانید.

## فهرست

| سند | موضوع |
|-----|--------|
| [trading/architecture.md](trading/architecture.md) | معماری کلی: evaluate → entry → monitor → exit |
| [trading/order-monitoring.md](trading/order-monitoring.md) | تفکیک `MonitorOrderJob` (buy) و `ManageExitJob` (sell) + فیکس scope |
| [trading/jobs-and-schedule.md](trading/jobs-and-schedule.md) | Jobها، schedule، و dispatch command |

## پیکربندی agentها

| ابزار | مسیر | نقش |
|-------|------|-----|
| **Cursor** | [`.cursor/rules/`](../.cursor/rules/) | قوانین path-gated و always-apply |
| **Cursor** | [`.cursor/skills/`](../.cursor/skills/) | skillهای پروژه‌ای |
| **Cursor** | [`AGENTS.md`](../AGENTS.md) | context سطح پروژه |
| **Claude Code** | [`CLAUDE.md`](../CLAUDE.md) | context سطح پروژه |
| **Claude Code** | [`.claude/rules/`](../.claude/rules/) | قوانین modular |
| **Claude Code** | [`.claude/skills/`](../.claude/skills/) | skillهای `/trading-order-monitoring` |

## اصول کلی

- **Entry (buy)** و **exit (sell)** دو مسیر جدا دارند؛ منطق entry را روی sell اعمال نکنید.
- تغییرات تریدینگ باید با تست‌های `tests/Feature/` هم‌راستا باشد.
- PHP **8.3+** برای اجرای تست‌ها لازم است (`composer.json`).
