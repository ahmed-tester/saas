<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\SettingKey;
use App\Enums\Settings\SettingType;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

class Settings
{
    public static function getInt(SettingKey $key): int
    {
        self::assertType($key, SettingType::Integer);

        return (int) self::get($key);
    }

    public static function getString(SettingKey $key): string
    {
        self::assertType($key, SettingType::String);

        return (string) self::get($key);
    }

    public static function getBool(SettingKey $key): bool
    {
        self::assertType($key, SettingType::Boolean);

        return (bool) self::get($key);
    }

    /**
     * @return array<mixed>
     */
    public static function getJson(SettingKey $key): array
    {
        self::assertType($key, SettingType::Json);

        $value = self::get($key);

        return is_array($value) ? $value : [];
    }

    /**
     * Read a typed platform setting, falling back to config when no row exists.
     */
    public static function get(SettingKey $key): int|string|bool|array
    {
        $setting = Setting::query()->find($key->value);

        if ($setting === null) {
            return self::configFallback($key);
        }

        $decoded = self::decodeScalar($setting->getRawOriginal('value') ?? $setting->value);

        if (! self::matchesType($key->type(), $decoded)) {
            return self::configFallback($key);
        }

        return $decoded;
    }

    /**
     * Read any setting by string key (core SettingKey or custom DB setting).
     */
    public static function getRaw(string $key, mixed $default = null): mixed
    {
        $enumKey = SettingKey::tryFrom($key);

        if ($enumKey !== null) {
            return self::get($enumKey);
        }

        $setting = Setting::query()->find($key);

        if ($setting === null) {
            return $default;
        }

        return self::decodeScalar($setting->getRawOriginal('value') ?? $setting->value);
    }

    /**
     * Return all effective settings belonging to a given group keyed by SettingKey->value.
     *
     * @return array<string, int|string|bool|array<mixed>>
     */
    public static function getGroup(SettingGroup $group): array
    {
        $result = [];

        foreach (SettingKey::cases() as $key) {
            if ($key->group() === $group) {
                $result[$key->value] = self::get($key);
            }
        }

        Setting::query()
            ->where('group', $group->value)
            ->orderBy('key')
            ->get()
            ->each(function (Setting $setting) use (&$result): void {
                if (! array_key_exists($setting->key, $result)) {
                    $result[$setting->key] = self::decodeScalar($setting->getRawOriginal('value') ?? $setting->value);
                }
            });

        return $result;
    }

    /**
     * Return custom DB settings (not defined in SettingKey) for a given group.
     *
     * @return Collection<int, Setting>
     */
    public static function customForGroup(SettingGroup $group): Collection
    {
        $coreKeys = array_map(static fn (SettingKey $key): string => $key->value, SettingKey::cases());

        return Setting::query()
            ->where('group', $group->value)
            ->whereNotIn('key', $coreKeys)
            ->orderBy('key')
            ->get();
    }

    public static function setInt(SettingKey $key, int $value): void
    {
        self::assertType($key, SettingType::Integer);
        self::set($key, $value);
    }

    /**
     * Persist a typed platform setting after validating its type and domain constraints.
     */
    public static function set(SettingKey $key, mixed $value): void
    {
        self::validateValue($key, $value);

        Setting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'group' => $key->group()->value,
                'type' => $key->type()->value,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
            ],
        );
    }

    /**
     * Persist a core or custom setting by string key with explicit group and type validation.
     */
    public static function setCustom(string $key, SettingGroup $group, SettingType $type, mixed $value): void
    {
        $key = trim($key);

        if ($key === '' || strlen($key) > 100 || preg_match('/^[a-z][a-z0-9_.]*$/', $key) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Setting key "%s" must start with a lowercase letter and contain only a-z, 0-9, _ or .', $key),
            );
        }

        $enumKey = SettingKey::tryFrom($key);

        if ($enumKey !== null) {
            self::set($enumKey, $value);

            return;
        }

        if (! self::matchesType($type, $value)) {
            throw new InvalidArgumentException(
                sprintf('Setting "%s" expects a value of type "%s".', $key, $type->value),
            );
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group->value,
                'type' => $type->value,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
            ],
        );
    }

    /**
     * Persist multiple platform settings atomically inside a single transaction.
     *
     * @param  array<string, mixed>  $values  Map of SettingKey->value => mixed
     */
    public static function setMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $rawKey => $value) {
                $key = SettingKey::from((string) $rawKey);
                self::set($key, $value);
            }
        });
    }

    /**
     * Persist multiple integer platform settings atomically inside a single transaction.
     *
     * @param  array<string, int>  $values  Map of SettingKey->value => int
     */
    public static function setManyInt(array $values): void
    {
        self::setMany($values);
    }

    public static function decodeValue(Setting $setting): mixed
    {
        return self::decodeScalar($setting->getRawOriginal('value') ?? $setting->value);
    }

    private static function assertType(SettingKey $key, SettingType $expected): void
    {
        if ($key->type() !== $expected) {
            throw new InvalidArgumentException(
                sprintf(
                    'Setting "%s" has type "%s", expected "%s".',
                    $key->value,
                    $key->type()->value,
                    $expected->value,
                ),
            );
        }
    }

    private static function validateValue(SettingKey $key, mixed $value): void
    {
        if (! self::matchesType($key->type(), $value)) {
            throw new InvalidArgumentException(
                sprintf('Setting "%s" expects a value of type "%s".', $key->value, $key->type()->value),
            );
        }

        if ($key->type() === SettingType::Integer) {
            $min = $key->minValue();

            if ($min !== null && $value < $min) {
                throw new InvalidArgumentException(
                    sprintf('Setting "%s" must be an integer >= %d; got %d.', $key->value, $min, $value),
                );
            }
        }
    }

    private static function matchesType(SettingType $type, mixed $value): bool
    {
        return match ($type) {
            SettingType::Integer => is_int($value),
            SettingType::String => is_string($value),
            SettingType::Boolean => is_bool($value),
            SettingType::Json => is_array($value),
        };
    }

    private static function configFallback(SettingKey $key): int|string|bool|array
    {
        $raw = config($key->configKey(), $key->defaultValue());

        return match ($key->type()) {
            SettingType::Integer => (int) $raw,
            SettingType::String => (string) $raw,
            SettingType::Boolean => (bool) $raw,
            SettingType::Json => is_array($raw) ? $raw : (array) $key->defaultValue(),
        };
    }

    private static function decodeScalar(mixed $raw): mixed
    {
        if (is_int($raw) || is_bool($raw) || is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw)) {
            return null;
        }

        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
}
