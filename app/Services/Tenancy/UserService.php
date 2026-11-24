<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\Billing\FeatureKey;
use App\Models\Tenancy\Tenant;
use App\Models\User;
use App\Services\Billing\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class UserService
{
    public function __construct(
        private readonly FeatureGate $featureGate,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    public function create(Tenant $tenant, array $data, array $roles = []): User
    {
        return TenantContext::central(function () use ($tenant, $data, $roles): User {
            $user = DB::transaction(function () use ($tenant, $data, $roles): User {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

                $limit = $this->featureGate->for($tenant)->limit(FeatureKey::MaxUsers);

                if ($limit === 0 || ($limit !== null && $tenant->users()->lockForUpdate()->count() >= $limit)) {
                    throw new RuntimeException(__('billing.user_limit_reached'));
                }

                /** @var User $user */
                $user = $tenant->users()->create([
                    'name' => $data['name'],
                    'email' => strtolower(trim((string) $data['email'])),
                    'password' => $data['password'],
                    'type' => $data['type'] ?? null,
                    'meta' => $data['meta'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                    'must_change_password' => $data['must_change_password'] ?? false,
                ]);

                if ($roles !== []) {
                    setPermissionsTeamId($tenant->id);
                    $user->syncRoles($roles);
                }

                return $user;
            }, attempts: 3);

            $this->permissionRegistrar->forgetCachedPermissions();

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $roles
     */
    public function update(Tenant $tenant, User $user, array $data, ?array $roles = null): User
    {
        return TenantContext::central(function () use ($tenant, $user, $data, $roles): User {
            DB::transaction(function () use ($tenant, $user, $data, $roles): void {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

                $lockedUser = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($user->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedUser === null) {
                    throw new InvalidArgumentException('User does not belong to the specified tenant.');
                }

                setPermissionsTeamId($tenant->id);

                $isOwner = $lockedUser->hasRole('owner');
                $removingOwnerRole = $roles !== null && $isOwner && ! in_array('owner', $roles, true);
                $deactivatingOwner = $isOwner && array_key_exists('is_active', $data) && ! (bool) $data['is_active'];

                if (($removingOwnerRole || $deactivatingOwner) && ! $this->hasOtherActiveOwner($tenant, $lockedUser)) {
                    throw new InvalidArgumentException(__('tenancy.cannot_remove_last_owner'));
                }

                $payload = array_intersect_key($data, array_flip([
                    'name',
                    'email',
                    'password',
                    'type',
                    'meta',
                    'is_active',
                    'must_change_password',
                ]));

                if (isset($payload['email'])) {
                    $payload['email'] = strtolower(trim((string) $payload['email']));
                }

                if ($payload !== []) {
                    $lockedUser->update($payload);
                }

                if ($roles !== null) {
                    setPermissionsTeamId($tenant->id);
                    $lockedUser->syncRoles($roles);
                }
            }, attempts: 3);

            $this->permissionRegistrar->forgetCachedPermissions();

            return $user->refresh();
        });
    }

    public function delete(Tenant $tenant, User $user): void
    {
        TenantContext::central(function () use ($tenant, $user): void {
            DB::transaction(function () use ($tenant, $user): void {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

                $lockedUser = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($user->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedUser === null) {
                    throw new InvalidArgumentException('User does not belong to the specified tenant.');
                }

                setPermissionsTeamId($tenant->id);

                if ($lockedUser->hasRole('owner') && ! $this->hasOtherActiveOwner($tenant, $lockedUser)) {
                    throw new InvalidArgumentException(__('tenancy.cannot_remove_last_owner'));
                }

                $lockedUser->delete();
            }, attempts: 3);

            $this->permissionRegistrar->forgetCachedPermissions();
        });
    }

    /**
     * @return array{user: User, password: string}
     */
    public function resetOwnerPassword(Tenant $tenant): array
    {
        return TenantContext::central(function () use ($tenant): array {
            setPermissionsTeamId($tenant->id);

            /** @var User $owner */
            $owner = $tenant->users()
                ->whereHas('roles', function ($query) use ($tenant): void {
                    $query->where('name', 'owner')
                        ->where('roles.tenant_id', $tenant->id);
                })
                ->firstOrFail();

            $temporaryPassword = Str::password(16);

            $owner->update([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);

            return [
                'user' => $owner->refresh(),
                'password' => $temporaryPassword,
            ];
        });
    }

    private function hasOtherActiveOwner(Tenant $tenant, User $excludingUser): bool
    {
        return $tenant->users()
            ->where('id', '!=', $excludingUser->id)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($tenant): void {
                $query->where('name', 'owner')
                    ->where('roles.tenant_id', $tenant->id);
            })
            ->lockForUpdate()
            ->exists();
    }
}
