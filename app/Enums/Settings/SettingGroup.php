<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum SettingGroup: string
{
    case General = 'general';
    case Billing = 'billing';
    case Mail = 'mail';
    case Payment = 'payment';

    public function label(): string
    {
        return __('admin.settings.groups.'.$this->value);
    }
}
