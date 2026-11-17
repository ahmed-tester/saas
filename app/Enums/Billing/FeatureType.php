<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum FeatureType: string
{
    case Boolean = 'boolean';
    case Limit = 'limit';

    public function label(): string
    {
        return __('billing.feature_type.' . $this->value);
    }
}
