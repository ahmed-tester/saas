<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Tenancy\TenantStatus;
use App\Filament\Admin\Resources\TenantResource;
use App\Models\Billing\Subscription;
use App\Models\Tenancy\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalTenants = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('status', TenantStatus::Active)->count();
        $trialTenants = Tenant::query()->where('status', TenantStatus::Trial)->count();
        $suspendedTenants = Tenant::query()->where('status', TenantStatus::Suspended)->count();

        $activeSubscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->count();

        $expiringSoon = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->count();

        $tenantsUrl = TenantResource::getUrl('index');

        return [
            Stat::make(__('admin.widgets.active_tenants'), (string) $activeTenants)
                ->description(__('admin.widgets.total_tenants_description', ['count' => $totalTenants]))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('success')
                ->url($tenantsUrl),

            Stat::make(__('admin.widgets.trial_tenants'), (string) $trialTenants)
                ->description(__('admin.widgets.trial_tenants_description'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url($tenantsUrl),

            Stat::make(__('admin.widgets.suspended_tenants'), (string) $suspendedTenants)
                ->description(__('admin.widgets.suspended_tenants_description'))
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color($suspendedTenants > 0 ? 'danger' : 'gray')
                ->url($tenantsUrl),

            Stat::make(__('admin.widgets.active_subscriptions'), (string) $activeSubscriptions)
                ->description(__('admin.widgets.expiring_soon_description', ['count' => $expiringSoon]))
                ->descriptionIcon('heroicon-m-credit-card')
                ->color($expiringSoon > 0 ? 'warning' : 'primary'),
        ];
    }
}
