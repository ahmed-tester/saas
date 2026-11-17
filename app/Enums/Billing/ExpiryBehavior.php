<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum ExpiryBehavior: string
{
    case Keep = 'keep';
    case ReadOnly = 'read_only';
    case Lock = 'lock';

    public function label(): string
    {
        return __('billing.expiry_behavior.' . $this->value);
    }
}
