<?php

namespace App\Filament\Pages;

use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\TradingSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class TradingSettings extends Page
{
    protected string $view = 'filament.pages.trading-settings';

    protected static ?string $navigationLabel = 'Trading Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    public array $settings = [];

    public function mount(TradingSettingsService $service): void
    {
        $service->syncDefaults();
        $this->settings = TradingSetting::query()->orderBy('key')->pluck('value', 'key')->toArray();
    }

    public function save(): void
    {
        foreach ($this->settings as $key => $value) {
            TradingSetting::query()->where('key', $key)->update(['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        Notification::make()->title('Trading settings saved')->success()->send();
    }
}
