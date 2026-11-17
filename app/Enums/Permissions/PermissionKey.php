<?php

declare(strict_types=1);

namespace App\Enums\Permissions;

use App\Enums\Billing\FeatureKey;

enum PermissionKey: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';

    public function feature(): ?FeatureKey
    {
        return match ($this) {
            self::UsersView,
            self::UsersCreate,
            self::UsersUpdate,
            self::UsersDelete,
            self::RolesView,
            self::RolesManage => null,
        };
    }

    public function label(): string
    {
        return __('permissions.key.' . $this->value);
    }
}
