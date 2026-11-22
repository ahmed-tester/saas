<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum SubscriptionState: string
{
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case None = 'none';

    public function isSubscribed(): bool
    {
        return $this === self::Active || $this === self::Grace;
    }

    public function label(): string
    {
        return __('billing.subscription_state.'.$this->value);
    }
}
