<?php

namespace Database\Seeders;

use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\Market;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        app(TradingSettingsService::class)->syncDefaults();

        Market::query()->firstOrCreate(
            ['exchange' => 'wallex', 'symbol' => 'BTCTMN'],
            [
                'base_asset' => 'BTC',
                'quote_asset' => 'TMN',
                'tick_size' => '1',
                'step_size' => '1',
                'min_order_amount' => '0',
                'is_active' => false,
            ],
        );
    }
}
