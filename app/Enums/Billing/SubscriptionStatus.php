<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Canceled = 'canceled';

    public function label(): string
    {
        return __('billing.subscription_status.'.$this->value);
    }
}
