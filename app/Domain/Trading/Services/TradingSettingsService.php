<?php

namespace App\Domain\Trading\Services;

use App\Models\TradingSetting;

class TradingSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return TradingSetting::value($key, config("trading.settings.{$key}", $default));
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public function decimal(string $key): string
    {
        return (string) $this->get($key, '0');
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    public function mode(): string
    {
        return (string) $this->get('trading_mode', config('trading.mode', 'paper'));
    }

    public function botEnabled(): bool
    {
        return (bool) config('trading.enabled', false) && $this->bool('bot_enabled');
    }

    public function syncDefaults(): void
    {
        foreach ((array) config('trading.settings', []) as $key => $value) {
            TradingSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                    'type' => is_bool($value) ? 'bool' : (is_numeric($value) ? 'decimal' : 'string'),
                    'label' => str($key)->headline()->toString(),
                    'is_public' => true,
                ],
            );
        }
    }
}
