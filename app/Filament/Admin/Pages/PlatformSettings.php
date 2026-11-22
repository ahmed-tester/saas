<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\SettingKey;
use App\Enums\Settings\SettingType;
use App\Models\Admin\SuperAdmin;
use App\Models\Setting;
use App\Services\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property-read Schema $form
 */
class PlatformSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 5;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentOrDefaultPanel()?->getId() === 'admin'
            && Auth::guard('admin')->user() instanceof SuperAdmin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.navigation_label');
    }

    public function getTitle(): string
    {
        return __('admin.settings.title');
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_setting')
                ->label(__('admin.settings.actions.add'))
                ->icon('heroicon-o-plus')
                ->schema([
                    Select::make('group')
                        ->label(__('admin.settings.fields.group'))
                        ->options(collect(SettingGroup::cases())->mapWithKeys(
                            static fn (SettingGroup $group): array => [$group->value => $group->label()],
                        ))
                        ->default(SettingGroup::General->value)
                        ->required(),
                    TextInput::make('key')
                        ->label(__('admin.settings.fields.key'))
                        ->helperText(__('admin.settings.helpers.key'))
                        ->regex('/^[a-z][a-z0-9_.]*$/')
                        ->maxLength(100)
                        ->required(),
                    Select::make('type')
                        ->label(__('admin.settings.fields.type'))
                        ->options(collect(SettingType::cases())->mapWithKeys(
                            static fn (SettingType $type): array => [$type->value => $type->label()],
                        ))
                        ->default(SettingType::String->value)
                        ->live()
                        ->required(),
                    TextInput::make('value_integer')
                        ->label(__('admin.settings.fields.value'))
                        ->numeric()
                        ->integer()
                        ->required()
                        ->visible(static fn (Get $get): bool => $get('type') === SettingType::Integer->value),
                    TextInput::make('value_string')
                        ->label(__('admin.settings.fields.value'))
                        ->required()
                        ->visible(static fn (Get $get): bool => ($get('type') ?? SettingType::String->value) === SettingType::String->value),
                    Toggle::make('value_boolean')
                        ->label(__('admin.settings.fields.value'))
                        ->default(false)
                        ->visible(static fn (Get $get): bool => $get('type') === SettingType::Boolean->value),
                    Textarea::make('value_json')
                        ->label(__('admin.settings.fields.value'))
                        ->helperText(__('admin.settings.helpers.json_value'))
                        ->rows(4)
                        ->json()
                        ->required()
                        ->visible(static fn (Get $get): bool => $get('type') === SettingType::Json->value),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);

                    $group = SettingGroup::from((string) $data['group']);
                    $type = SettingType::from((string) $data['type']);

                    $value = match ($type) {
                        SettingType::Integer => (int) $data['value_integer'],
                        SettingType::String => (string) $data['value_string'],
                        SettingType::Boolean => (bool) ($data['value_boolean'] ?? false),
                        SettingType::Json => json_decode((string) $data['value_json'], true, 512, JSON_THROW_ON_ERROR),
                    };

                    Settings::setCustom((string) $data['key'], $group, $type, $value);

                    $this->fillForm();

                    Notification::make()
                        ->title(__('admin.settings.notifications.added'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (SettingGroup::cases() as $group) {
            $components = $this->buildGroupComponents($group);

            $tabs[] = Tab::make($group->label())
                ->schema([
                    Section::make()
                        ->schema(
                            $components !== []
                                ? $components
                                : [
                                    TextEntry::make("empty_{$group->value}")
                                        ->hiddenLabel()
                                        ->state(__('admin.settings.empty_group')),
                                ],
                        )
                        ->columns($components !== [] ? 2 : 1),
                ]);
        }

        return $schema
            ->components([
                Tabs::make('settings_groups')
                    ->contained(false)
                    ->tabs($tabs),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('admin.settings.actions.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        DB::transaction(function () use ($state): void {
            foreach (SettingKey::cases() as $key) {
                $field = self::stateKey($key->value);

                if (! array_key_exists($field, $state)) {
                    continue;
                }

                Settings::set($key, $this->castInputValue($key->type(), $state[$field]));
            }

            $coreKeys = array_map(static fn (SettingKey $key): string => $key->value, SettingKey::cases());

            Setting::query()
                ->whereNotIn('key', $coreKeys)
                ->get()
                ->each(function (Setting $setting) use ($state): void {
                    $field = self::stateKey($setting->key);

                    if (! array_key_exists($field, $state)) {
                        return;
                    }

                    Settings::setCustom(
                        $setting->key,
                        $setting->group,
                        $setting->type,
                        $this->castInputValue($setting->type, $state[$field]),
                    );
                });
        });

        Notification::make()
            ->title(__('admin.settings.notifications.saved'))
            ->success()
            ->send();
    }

    private function fillForm(): void
    {
        $state = [];

        foreach (SettingKey::cases() as $key) {
            $value = Settings::get($key);
            $state[self::stateKey($key->value)] = $key->type() === SettingType::Json
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : $value;
        }

        $coreKeys = array_map(static fn (SettingKey $key): string => $key->value, SettingKey::cases());

        Setting::query()
            ->whereNotIn('key', $coreKeys)
            ->get()
            ->each(function (Setting $setting) use (&$state): void {
                $decoded = Settings::decodeValue($setting);
                $state[self::stateKey($setting->key)] = $setting->type === SettingType::Json
                    ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    : $decoded;
            });

        $this->form->fill($state);
    }

    /**
     * @return list<Component>
     */
    private function buildGroupComponents(SettingGroup $group): array
    {
        $components = [];

        foreach (SettingKey::cases() as $key) {
            if ($key->group() !== $group) {
                continue;
            }

            $components[] = $this->makeCoreField($key);
        }

        Settings::customForGroup($group)->each(function (Setting $setting) use (&$components): void {
            $components[] = $this->makeField(
                name: self::stateKey($setting->key),
                label: $setting->key,
                type: $setting->type,
                helperText: $setting->type->label(),
            );
        });

        return $components;
    }

    private function makeCoreField(SettingKey $key): Component
    {
        $field = $this->makeField(
            name: self::stateKey($key->value),
            label: $key->label(),
            type: $key->type(),
            helperText: match ($key) {
                SettingKey::TrialDays => __('admin.settings.helpers.trial_days'),
                SettingKey::GraceDays => __('admin.settings.helpers.grace_days'),
            },
        );

        if ($field instanceof TextInput && $key->type() === SettingType::Integer && $key->minValue() !== null) {
            $field->minValue($key->minValue());
            $field->validationMessages([
                'min' => match ($key) {
                    SettingKey::TrialDays => __('admin.settings.validation.trial_days_min'),
                    SettingKey::GraceDays => __('admin.settings.validation.grace_days_min'),
                },
            ]);
        }

        return $field;
    }

    private function makeField(string $name, string $label, SettingType $type, ?string $helperText = null): Component
    {
        return match ($type) {
            SettingType::Integer => TextInput::make($name)
                ->label($label)
                ->helperText($helperText)
                ->numeric()
                ->integer()
                ->required(),
            SettingType::String => TextInput::make($name)
                ->label($label)
                ->helperText($helperText)
                ->required(),
            SettingType::Boolean => Toggle::make($name)
                ->label($label)
                ->helperText($helperText),
            SettingType::Json => Textarea::make($name)
                ->label($label)
                ->helperText($helperText)
                ->rows(4)
                ->json()
                ->required()
                ->validationMessages([
                    'json' => __('admin.settings.validation.invalid_json'),
                ]),
        };
    }

    private function castInputValue(SettingType $type, mixed $value): mixed
    {
        return match ($type) {
            SettingType::Integer => (int) $value,
            SettingType::String => (string) $value,
            SettingType::Boolean => (bool) $value,
            SettingType::Json => is_array($value)
                ? $value
                : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR),
        };
    }

    public static function stateKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
