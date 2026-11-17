<?php

declare(strict_types=1);

namespace App\Enums\Tenancy;

enum TenantStatus: string
{
    case Active = 'active';
    case Trial = 'trial';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('tenancy.status.' . $this->value);
    }
}
