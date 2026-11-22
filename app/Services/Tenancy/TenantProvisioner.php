<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Admin\SuperAdmin;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Tenancy\Domain;
use App\Models\Tenancy\Tenant;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class TenantProvisioner
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly RoleService $roleService,
        private readonly SubscriptionService $subscriptionService,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     slug: string,
     *     owner_name: string,
     *     owner_email: string,
     *     plan_id?: int|null,
     *     owner_password?: string|null,
     * }  $data
     * @return array{
     *     tenant: Tenant,
     *     domain: Domain,
     *     owner: User,
     *     temporary_password: string,
     *     subscription: Subscription,
     * }
     */
    public function provision(array $data, ?SuperAdmin $actor = null): array
    {
        $result = TenantContext::central(fn (): array => DB::transaction(function () use ($data, $actor): array {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'status' => TenantStatus::Trial,
            ]);

            $domain = $this->domainService->addSubdomain($tenant, $data['slug'], isPrimary: true);

            $ownerRole = $this->roleService->ensureOwnerRole($tenant);

            $temporaryPassword = $data['owner_password'] ?? Str::password(16);

            /** @var User $owner */
            $owner = $tenant->users()->create([
                'name' => $data['owner_name'],
                'email' => strtolower(trim($data['owner_email'])),
                'password' => $temporaryPassword,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            setPermissionsTeamId($tenant->id);
            $owner->assignRole($ownerRole);

            $plan = isset($data['plan_id'])
                ? Plan::query()->findOrFail($data['plan_id'])
                : Plan::query()->where('slug', 'basic')->firstOrFail();

            $subscription = $this->subscriptionService->createTrial($tenant, $plan, $actor);
            $tenant->update(['trial_ends_at' => $subscription->ends_at]);

            return [
                'tenant' => $tenant,
                'domain' => $domain,
                'owner' => $owner,
                'temporary_password' => $temporaryPassword,
                'subscription' => $subscription,
            ];
        }));

        $this->permissionRegistrar->forgetCachedPermissions();

        return $result;
    }
}
