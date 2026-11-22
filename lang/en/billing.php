<?php

declare(strict_types=1);

return [
    'period' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],
    'expiry_behavior' => [
        'keep' => 'Keep Open',
        'read_only' => 'Read Only',
        'lock' => 'Lock',
    ],
    'subscription_state' => [
        'active' => 'Active',
        'grace' => 'Grace Period',
        'expired' => 'Expired',
        'none' => 'No Subscription',
    ],
    'subscription_status' => [
        'trial' => 'Trial',
        'active' => 'Active',
        'canceled' => 'Canceled',
    ],
    'subscription_event' => [
        'activated' => 'Activated',
        'renewed' => 'Renewed',
        'plan_changed' => 'Plan Changed',
        'extended' => 'Extended',
        'canceled' => 'Canceled',
    ],
    'feature' => [
        'invoicing' => 'Invoicing',
        'inventory' => 'Inventory',
        'data_export' => 'Data Export',
        'multi_warehouse' => 'Multi-Warehouse',
        'advanced_reports' => 'Advanced Reports',
        'barcode_printing' => 'Barcode Printing',
        'max_users' => 'Max Users',
        'max_products' => 'Max Products',
    ],
    'feature_type' => [
        'boolean' => 'Boolean',
        'limit' => 'Numeric Limit',
    ],
    'feature_locked' => 'This feature is locked. Please upgrade your plan.',
    'feature_read_only' => 'This feature is in read-only mode because your subscription has expired.',
    'user_limit_reached' => 'Maximum number of users for your plan has been reached.',
    'feature_not_available_for_tenant' => 'One or more selected permissions belong to features not available for this store.',
    'cannot_renew_endless' => 'An active subscription without an end date cannot be renewed.',
    'cannot_extend_endless' => 'An active subscription without an end date cannot be extended.',
    'cannot_extend_canceled' => 'A canceled subscription cannot be extended.',
    'cannot_renew_non_active' => 'Only active subscriptions with an end date can be renewed.',
    'cannot_activate_active' => 'The subscription is already active.',
    'invalid_starts_at' => 'Subscription start date cannot be in the future.',
    'invalid_past_due' => 'The past_due status is not supported in Phase 1.',
    'invalid_extend_days' => 'Extension days must be a positive integer.',
];
