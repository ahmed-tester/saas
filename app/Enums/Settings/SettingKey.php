<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum SettingKey: string
{
    case TrialDays = 'billing.trial_days';
    case GraceDays = 'billing.grace_days';

    public function group(): SettingGroup
    {
        return match ($this) {
            self::TrialDays, self::GraceDays => SettingGroup::Billing,
        };
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::TrialDays, self::GraceDays => SettingType::Integer,
        };
    }

    public function configKey(): string
    {
        return $this->value;
    }

    public function defaultValue(): int|string|bool|array
    {
        return match ($this) {
            self::TrialDays => 14,
            self::GraceDays => 7,
        };
    }

    public function minValue(): ?int
    {
        return match ($this) {
            self::TrialDays => 1,
            self::GraceDays => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TrialDays => __('admin.settings.fields.trial_days'),
            self::GraceDays => __('admin.settings.fields.grace_days'),
        };
    }
}
