<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum SettingType: string
{
    case Integer = 'integer';
    case String = 'string';
    case Boolean = 'boolean';
    case Json = 'json';

    public function label(): string
    {
        return __('admin.settings.types.'.$this->value);
    }
}
