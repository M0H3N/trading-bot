<?php

namespace App\Filament\Pages;

use App\Domain\Trading\Services\TradingSettingsService;
use App\Models\TradingSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class TradingSettings extends Page
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static ?string $navigationLabel = 'Trading settings';

    protected static ?string $title = 'Trading settings';

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 5;

    protected Width|string|null $maxContentWidth = Width::FiveExtraLarge;

    public function mount(TradingSettingsService $service): void
    {
        $service->syncDefaults();
        $this->form->fill($this->loadSettingsState());
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadSettingsState(): array
    {
        return TradingSetting::query()
            ->orderBy('key')
            ->get()
            ->mapWithKeys(function (TradingSetting $row): array {
                $value = match ($row->type) {
                    'bool' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
                    'int' => (int) $row->value,
                    default => (string) $row->value,
                };

                return [$row->key => $value];
            })
            ->all();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $byKey = TradingSetting::query()->orderBy('key')->get()->keyBy('key');

        $sections = [];
        foreach ($this->settingSections() as $block) {
            $fields = [];
            foreach ($block['keys'] as $key) {
                $record = $byKey->get($key);
                if (! $record instanceof TradingSetting) {
                    continue;
                }
                $fields[] = $this->fieldForRecord($record);
            }

            if ($fields === []) {
                continue;
            }

            $section = Section::make($block['title'])
                ->description($block['description'] ?? null)
                ->schema($fields)
                ->columns(2)
                ->columnSpanFull();

            $sections[] = $section;
        }

        return $schema->components($sections);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('trading-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment(Alignment::Start)
                            ->key('trading-settings-form-actions'),
                    ]),
            ]);
    }

    /**
     * @return array<int, array{title: string, description?: string, keys: list<string>}>
     */
    protected function settingSections(): array
    {
        return [
            [
                'title' => 'Bot & execution mode',
                'description' => 'Control whether the bot runs and whether orders are simulated (paper) or sent to the exchange (live).',
                'keys' => ['bot_enabled', 'trading_mode'],
            ],
            [
                'title' => 'Entry & book',
                'description' => 'How aggressively to enter and how much of the order book to consider for pricing.',
                'keys' => ['entry_threshold_percent', 'trade_balance_percent', 'depth_usd', 'tick_offset'],
            ],
            [
                'title' => 'Exit ladder',
                'description' => 'Parameters for scaling out of positions and pegging exit orders to the book.',
                'keys' => ['initial_exit_percent', 'exit_step_percent', 'exit_top_ask_from_percent', 'minimum_exit_percent'],
            ],
            [
                'title' => 'Risk & circuit breaker',
                'description' => 'Hard risk limits expressed as percentages or quote currency thresholds.',
                'keys' => ['stop_loss_percent', 'blocker_threshold_tmn'],
            ],
        ];
    }

    protected function fieldForRecord(TradingSetting $record): TextInput|Toggle|Select
    {
        $key = $record->key;
        $label = $record->label ?: Str::headline($key);
        $helper = $record->description;

        return match ($key) {
            'bot_enabled' => Toggle::make($key)
                ->label($label)
                ->helperText($helper ?? 'When off, evaluation jobs will not open new deals.')
                ->inline(false),
            'trading_mode' => Select::make($key)
                ->label($label)
                ->helperText($helper ?? 'Paper uses simulated fills; live sends real API orders.')
                ->options([
                    'paper' => 'Paper — simulated execution',
                    'live' => 'Live — real exchange orders',
                ])
                ->native(false)
                ->required(),
            'tick_offset' => TextInput::make($key)
                ->label($label)
                ->helperText($helper ?? 'Whole-number offset from the top-of-book price (ticks).')
                ->numeric()
                ->minValue(0)
                ->required(),
            'blocker_threshold_tmn' => TextInput::make($key)
                ->label($label)
                ->helperText($helper ?? 'Quote balance threshold (TMN) used for safety checks.')
                ->numeric()
                ->minValue(0)
                ->suffix('TMN')
                ->required(),
            'depth_usd' => TextInput::make($key)
                ->label($label)
                ->helperText($helper)
                ->numeric()
                ->minValue(0)
                ->suffix('USD')
                ->required(),
            default => $this->defaultNumericPercentField($key, $label, $helper),
        };
    }

    protected function defaultNumericPercentField(string $key, string $label, ?string $helper): TextInput
    {
        $input = TextInput::make($key)
            ->label($label)
            ->helperText($helper)
            ->numeric()
            ->required();

        if (Str::endsWith($key, '_percent')) {
            $input->suffix('%');
        }

        return $input;
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            TradingSetting::query()->where('key', $key)->update([
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            ]);
        }

        Notification::make()
            ->title('Trading settings saved')
            ->success()
            ->send();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Strategy thresholds, sizing, and safety limits used by the trading jobs.';
    }
}
