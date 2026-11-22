<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\Permissions\PermissionKey;
use App\Models\Tenancy\Tenant;
use App\Models\User;
use App\Services\Tenancy\RoleService;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\UserService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FeatureSeeder::class, PlanSeeder::class, PermissionSeeder::class]);

        $provisioner = app(TenantProvisioner::class);
        $a = $provisioner->provision([
            'name' => 'Shop A',
            'slug' => 'perma',
            'owner_name' => 'Owner A',
            'owner_email' => 'ownera@perm.test',
        ]);
        $b = $provisioner->provision([
            'name' => 'Shop B',
            'slug' => 'permb',
            'owner_name' => 'Owner B',
            'owner_email' => 'ownerb@perm.test',
        ]);

        $this->tenantA = $a['tenant'];
        $this->ownerA = $a['owner'];
        $this->tenantB = $b['tenant'];
    }

    public function test_same_role_name_in_two_tenants_does_not_collide_or_leak(): void
    {
        $roleService = app(RoleService::class);
        $userService = app(UserService::class);

        $roleA = $roleService->create($this->tenantA, 'cashier', [PermissionKey::UsersView]);
        $roleB = $roleService->create($this->tenantB, 'cashier', [PermissionKey::RolesView]);

        $userA = $userService->create($this->tenantA, [
            'name' => 'User A',
            'email' => 'ua@perm.test',
            'password' => 'Secret123!',
        ], ['cashier']);

        $userB = $userService->create($this->tenantB, [
            'name' => 'User B',
            'email' => 'ub@perm.test',
            'password' => 'Secret123!',
        ], ['cashier']);

        // Editing role in tenant A does not affect tenant B
        $roleService->update($this->tenantA, $roleA, 'cashier', [PermissionKey::UsersView, PermissionKey::UsersCreate]);

        tenancy()->initialize($this->tenantA);
        $this->assertTrue($userA->can(PermissionKey::UsersCreate->value));
        $this->assertFalse($userA->can(PermissionKey::RolesView->value));
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $this->assertTrue($userB->can(PermissionKey::RolesView->value));
        $this->assertFalse($userB->can(PermissionKey::UsersView->value));
        $this->assertSame([PermissionKey::RolesView->value], $roleB->fresh()->permissions->pluck('name')->all());
        tenancy()->end();
    }

    public function test_user_with_multiple_roles_gets_union_and_removing_one_drops_only_its_permissions(): void
    {
        $roleService = app(RoleService::class);
        $userService = app(UserService::class);

        $roleService->create($this->tenantA, 'viewer', [PermissionKey::UsersView]);
        $roleService->create($this->tenantA, 'editor', [PermissionKey::UsersUpdate]);

        $user = $userService->create($this->tenantA, [
            'name' => 'Multi Role',
            'email' => 'multi@perm.test',
            'password' => 'Secret123!',
        ], ['viewer', 'editor']);

        tenancy()->initialize($this->tenantA);
        $this->assertTrue($user->can(PermissionKey::UsersView->value));
        $this->assertTrue($user->can(PermissionKey::UsersUpdate->value));

        $userService->update($this->tenantA, $user, [], ['viewer']);
        $user->refresh();

        $this->assertTrue($user->can(PermissionKey::UsersView->value));
        $this->assertFalse($user->can(PermissionKey::UsersUpdate->value));
        tenancy()->end();
    }

    public function test_owner_passes_permissions_only_within_own_tenant_context(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->assertTrue($this->ownerA->can(PermissionKey::UsersDelete->value));
        tenancy()->end();

        // In tenant B context, ownerA must NOT pass Gate::before
        tenancy()->initialize($this->tenantB);
        $this->assertFalse($this->ownerA->can(PermissionKey::UsersDelete->value));
        tenancy()->end();
    }

    public function test_user_service_enforces_max_users_limit_and_last_owner_guard(): void
    {
        $userService = app(UserService::class);

        // Basic plan has max_users = 3 (ownerA is already 1)
        $user2 = $userService->create($this->tenantA, [
            'name' => 'U2',
            'email' => 'u2@perm.test',
            'password' => 'Secret123!',
        ]);
        $userService->create($this->tenantA, [
            'name' => 'U3',
            'email' => 'u3@perm.test',
            'password' => 'Secret123!',
        ]);

        try {
            $userService->create($this->tenantA, [
                'name' => 'U4',
                'email' => 'u4@perm.test',
                'password' => 'Secret123!',
            ]);
            $this->fail('Expected RuntimeException when max_users limit is reached.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('billing.user_limit_reached'), $e->getMessage());
        }

        // Removing/deactivating/deleting last owner must fail
        $this->expectException(InvalidArgumentException::class);
        $userService->delete($this->tenantA, $this->ownerA);

        $this->assertNotNull($user2);
    }

    public function test_role_service_prevents_modifying_owner_role(): void
    {
        $roleService = app(RoleService::class);
        $ownerRole = $roleService->ensureOwnerRole($this->tenantA);

        $this->expectException(InvalidArgumentException::class);
        $roleService->delete($this->tenantA, $ownerRole);
    }
}
