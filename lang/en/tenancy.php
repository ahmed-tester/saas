<?php

declare(strict_types=1);

return [
    'status' => [
        'active' => 'Active',
        'trial' => 'Trial',
        'suspended' => 'Suspended',
    ],
    'domain_type' => [
        'subdomain' => 'Subdomain',
        'custom' => 'Custom Domain',
    ],
    'suspended' => 'This store account is currently suspended.',
    'user_inactive' => 'Your account is inactive.',
    'wrong_tenant' => 'These credentials do not match our records.',
    'slug_invalid' => 'The slug may only contain lowercase letters, numbers, and hyphens, must be 3–32 characters, and cannot start or end with a hyphen.',
    'slug_reserved' => 'This slug is reserved and cannot be used.',
    'slug_taken' => 'This subdomain slug is already taken.',
    'cannot_delete_primary_domain' => 'Cannot delete the primary domain.',
    'cannot_set_unverified_primary' => 'An unverified custom domain cannot be set as primary.',
    'cannot_remove_last_owner' => 'At least one active owner must remain in the store.',
    'cannot_modify_owner_role' => 'The owner role cannot be modified or deleted.',
];
