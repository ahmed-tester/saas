<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('billing.period.'.$this->value);
    }
}
