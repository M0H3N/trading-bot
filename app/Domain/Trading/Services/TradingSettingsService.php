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

    public function entryMinOrderSum(string $quoteAsset): float
    {
        return match (strtoupper($quoteAsset)) {
            'USDT' => (float) $this->decimal('min_order_sum_usdt'),
            default => (float) $this->decimal('min_order_sum_tmn'),
        };
    }

    public function exitMinOrderSum(string $quoteAsset): float
    {
        return match (strtoupper($quoteAsset)) {
            'USDT' => (float) '1',
            default => (float) '50000',
        };
    }

    public function marketEvaluationEnabled(): bool
    {
        return $this->bool('market_evaluation_enabled');
    }

    public function exitManagementEnabled(): bool
    {
        return $this->bool('exit_management_enabled') || $this->bool('market_evaluation_enabled');
    }

    public function syncDefaults(): void
    {
        $legacy = TradingSetting::query()->where('key', 'bot_enabled')->first();

        if ($legacy) {
            $enabled = filter_var($legacy->value, FILTER_VALIDATE_BOOLEAN);
            $legacyValue = $enabled ? '1' : '0';

            foreach (['market_evaluation_enabled', 'exit_management_enabled'] as $key) {
                TradingSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $legacyValue,
                        'type' => 'bool',
                        'label' => str($key)->headline()->toString(),
                        'is_public' => true,
                    ],
                );
            }

            $legacy->delete();
        }

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
