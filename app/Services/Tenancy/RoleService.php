<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\Permissions\PermissionKey;
use App\Models\Tenancy\Tenant;
use App\Services\Billing\FeatureGate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private readonly FeatureGate $featureGate,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function ensureOwnerRole(Tenant $tenant): Role
    {
        /** @var Role $role */
        $role = Role::query()->firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        return $role;
    }

    /**
     * @param  list<string|PermissionKey>  $permissions
     */
    public function create(Tenant $tenant, string $name, array $permissions = []): Role
    {
        $normalizedName = trim($name);

        if (strtolower($normalizedName) === 'owner') {
            throw new InvalidArgumentException(__('tenancy.cannot_modify_owner_role'));
        }

        $permissionNames = $this->validatePermissionsForTenant($tenant, $permissions);

        $role = DB::transaction(function () use ($tenant, $normalizedName, $permissionNames): Role {
            setPermissionsTeamId($tenant->id);

            /** @var Role $role */
            $role = Role::query()->create([
                'name' => $normalizedName,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);

            $role->syncPermissions($permissionNames);

            return $role;
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $role;
    }

    /**
     * @param  list<string|PermissionKey>  $permissions
     */
    public function update(Tenant $tenant, Role $role, string $name, array $permissions): Role
    {
        if ((int) $role->getAttribute('tenant_id') !== $tenant->id) {
            throw new InvalidArgumentException('Role does not belong to the specified tenant.');
        }

        if ($role->name === 'owner') {
            throw new InvalidArgumentException(__('tenancy.cannot_modify_owner_role'));
        }

        $normalizedName = trim($name);

        if (strtolower($normalizedName) === 'owner') {
            throw new InvalidArgumentException(__('tenancy.cannot_modify_owner_role'));
        }

        $permissionNames = $this->validatePermissionsForTenant($tenant, $permissions);

        DB::transaction(function () use ($tenant, $role, $normalizedName, $permissionNames): void {
            setPermissionsTeamId($tenant->id);

            $role->update(['name' => $normalizedName]);
            $role->syncPermissions($permissionNames);
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $role->refresh();
    }

    public function delete(Tenant $tenant, Role $role): void
    {
        if ((int) $role->getAttribute('tenant_id') !== $tenant->id) {
            throw new InvalidArgumentException('Role does not belong to the specified tenant.');
        }

        if ($role->name === 'owner') {
            throw new InvalidArgumentException(__('tenancy.cannot_modify_owner_role'));
        }

        setPermissionsTeamId($tenant->id);
        $role->delete();
        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * @param  list<string|PermissionKey>  $permissions
     * @return list<string>
     */
    private function validatePermissionsForTenant(Tenant $tenant, array $permissions): array
    {
        $gate = $this->featureGate->for($tenant);
        $names = [];

        foreach ($permissions as $permission) {
            $key = $permission instanceof PermissionKey
                ? $permission
                : PermissionKey::from($permission);

            $feature = $key->feature();

            if ($feature !== null && ! $gate->canRead($feature)) {
                throw new InvalidArgumentException(__('billing.feature_not_available_for_tenant'));
            }

            $names[] = $key->value;
        }

        return array_values(array_unique($names));
    }
}
