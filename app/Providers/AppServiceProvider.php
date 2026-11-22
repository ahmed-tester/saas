<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permissions\PermissionKey;
use App\Models\User;
use App\Services\Billing\FeatureGate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            if (! tenancy()->initialized) {
                return null;
            }

            $tenantId = (int) tenant()->getTenantKey();

            if ((int) $user->tenant_id !== $tenantId || (int) getPermissionsTeamId() !== $tenantId) {
                return null;
            }

            if (! $user->hasRole('owner')) {
                return null;
            }

            $permission = PermissionKey::tryFrom($ability);
            $feature = $permission?->feature();

            if ($feature !== null) {
                $gate = app(FeatureGate::class);
                $isRead = str_ends_with($ability, '.view');

                if ($isRead ? ! $gate->canRead($feature) : ! $gate->canWrite($feature)) {
                    return false;
                }
            }

            return true;
        });
    }
}
