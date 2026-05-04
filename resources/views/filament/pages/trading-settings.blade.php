<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($settings as $key => $value)
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ str($key)->headline() }}</span>
                        @if (in_array($key, ['bot_enabled'], true))
                            <select wire:model="settings.{{ $key }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                                <option value="0">Off</option>
                                <option value="1">On</option>
                            </select>
                        @elseif ($key === 'trading_mode')
                            <select wire:model="settings.{{ $key }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                                <option value="paper">Paper Trading</option>
                                <option value="live">Live Trading</option>
                            </select>
                        @else
                            <input wire:model="settings.{{ $key }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                        @endif
                    </label>
                @endforeach
            </div>
        </div>

        <x-filament::button wire:click="save">Save settings</x-filament::button>
    </div>
</x-filament-panels::page>
