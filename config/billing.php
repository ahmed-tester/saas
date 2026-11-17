<?php

declare(strict_types=1);

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
    'default_expiry_behavior' => 'lock',
    'reserved_slugs' => [
        'www',
        'admin',
        'app',
        'api',
        'mail',
        'static',
        'assets',
        'cdn',
        'support',
        'status',
    ],
];
