<?php

declare(strict_types=1);

namespace App\Enums\Tenancy;

enum DomainType: string
{
    case Subdomain = 'subdomain';
    case Custom = 'custom';

    public function label(): string
    {
        return __('tenancy.domain_type.' . $this->value);
    }
}
