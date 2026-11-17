<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum SubscriptionEventType: string
{
    case Activated = 'activated';
    case Renewed = 'renewed';
    case PlanChanged = 'plan_changed';
    case Extended = 'extended';
    case Canceled = 'canceled';

    public function label(): string
    {
        return __('billing.subscription_event.' . $this->value);
    }
}
