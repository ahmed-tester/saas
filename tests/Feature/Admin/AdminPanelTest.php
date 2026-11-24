<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Billing\FeatureKey;
use App\Filament\Admin\Resources\FeatureResource\Pages\EditFeature;
use App\Filament\Admin\Resources\FeatureResource\Pages\ListFeatures;
use App\Filament\Admin\Resources\PlanResource\Pages\CreatePlan;
use App\Filament\Admin\Resources\PlanResource\Pages\EditPlan;
use App\Filament\Admin\Resources\PlanResource\Pages\ListPlans;
use App\Filament\Admin\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Admin\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\DomainsRelationManager;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\SubscriptionEventsRelationManager;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\TenantFeaturesRelationManager;
use App\Filament\Admin\Widgets\AdminStatsOverview;
use App\Filament\Admin\Widgets\ExpiringSubscriptionsWidget;
use App\Filament\Admin\Widgets\RecentSubscriptionEventsWidget;
use App\Models\Admin\SuperAdmin;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\TenantFeature;
use App\Models\Tenancy\Tenant;
use App\Services\Tenancy\DomainService;
use App\Services\Tenancy\TenantProvisioner;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.base_domain', 'app.test');
        config()->set('app.admin_domain', 'admin.app.test');
        config()->set('tenancy.central_domains', ['admin.app.test', 'app.test']);

        $this->seed([FeatureSeeder::class, PlanSeeder::class, PermissionSeeder::class]);

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@app.test',
            'password' => 'Secret123!',
            'is_active' => true,
        ]);
    }

    public function test_filament_admin_panel_loads_on_admin_domain_only_and_returns_404_on_tenant_domain(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Shop One',
            'slug' => 'shop1',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@shop1.test',
        ], $this->admin);

        $this->actingAs($this->admin, 'admin');

        $this->get('http://admin.app.test/admin')->assertOk();
        $this->get('http://admin.app.test/admin/tenants')->assertOk();

        // Livewire component renders & responds on admin domain
        Livewire::test(ListTenants::class)->assertSuccessful();

        // Real HTTP Livewire update request succeeds on admin domain
        $tenantsPage = $this->get('http://admin.app.test/admin/tenants')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $tenantsPage->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $updatePath = '/'.ltrim(route('default-livewire.update', [], false), '/');

        $this->postJson("http://admin.app.test{$updatePath}", [
            '_token' => csrf_token(),
            'components' => [
                [
                    'snapshot' => $snapshot,
                    'updates' => [],
                    'calls' => [],
                ],
            ],
        ], [
            'X-Livewire' => 'true',
            'X-CSRF-TOKEN' => csrf_token(),
        ])->assertOk();

        // /admin on tenant domain must 404
        $this->get('http://shop1.app.test/admin')->assertNotFound();

        // Admin guard cannot access tenant dashboard
        $this->get('http://shop1.app.test/')->assertRedirect('http://shop1.app.test/login');
        $this->assertNotNull($provisioned['tenant']);
    }

    public function test_web_guard_cannot_access_admin_panel(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Shop Two',
            'slug' => 'shop2',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@shop2.test',
        ], $this->admin);

        $this->actingAs($provisioned['owner'], 'web');

        $this->get('http://admin.app.test/admin')->assertRedirect('http://admin.app.test/admin/login');
    }

    public function test_unverified_custom_domain_cannot_be_set_as_primary_and_primary_cannot_be_deleted(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Domain Shop',
            'slug' => 'dom1',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@dom1.test',
        ], $this->admin);

        /** @var Tenant $tenant */
        $tenant = $provisioned['tenant'];
        $domainService = app(DomainService::class);
        $custom = $domainService->addCustomDomain($tenant, 'custom.dom1.test');

        try {
            $domainService->setPrimary($custom);
            $this->fail('Expected InvalidArgumentException when setting unverified domain as primary.');
        } catch (InvalidArgumentException) {
            $this->assertFalse($custom->fresh()->is_primary);
        }

        $this->expectException(InvalidArgumentException::class);
        $domainService->delete($provisioned['domain']);
    }

    public function test_admin_dashboard_renders_custom_widgets_instead_of_default_widgets(): void
    {
        app(TenantProvisioner::class)->provision([
            'name' => 'Dashboard Shop',
            'slug' => 'dash1',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@dash1.test',
        ], $this->admin);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get('http://admin.app.test/admin');
        $response->assertOk();
        $response->assertDontSee('fi-filament-info-widget');
        $response->assertDontSee('fi-account-widget');
        $response->assertSee('AdminStatsOverview');
        $response->assertSee('ExpiringSubscriptionsWidget');
        $response->assertSee('RecentSubscriptionEventsWidget');

        Livewire::test(AdminStatsOverview::class)
            ->assertSuccessful()
            ->assertSee(__('admin.widgets.active_tenants'));
        Livewire::test(ExpiringSubscriptionsWidget::class)
            ->assertSuccessful()
            ->assertSee(__('admin.widgets.expiring_subscriptions_title'));
        Livewire::test(RecentSubscriptionEventsWidget::class)
            ->assertSuccessful()
            ->assertSee(__('admin.widgets.recent_subscription_events_title'));
    }

    public function test_admin_phase_1_screens_and_tenant_features_relation_manager_work(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Plans resource pages
        $this->get('http://admin.app.test/admin/plans')->assertOk();
        Livewire::test(ListPlans::class)->assertSuccessful();
        Livewire::test(CreatePlan::class)->assertSuccessful();
        $plan = Plan::query()->firstOrFail();
        Livewire::test(EditPlan::class, ['record' => $plan->getRouteKey()])->assertSuccessful();

        // Features catalog resource pages
        $this->get('http://admin.app.test/admin/features')->assertOk();
        Livewire::test(ListFeatures::class)->assertSuccessful();
        $feature = Feature::query()->where('key', FeatureKey::AdvancedReports)->firstOrFail();
        Livewire::test(EditFeature::class, ['record' => $feature->getRouteKey()])->assertSuccessful();

        // Tenants resource pages + relation managers
        Livewire::test(CreateTenant::class)->assertSuccessful();
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Features UI Shop',
            'slug' => 'featui',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@featui.test',
        ], $this->admin);

        /** @var Tenant $tenant */
        $tenant = $provisioned['tenant'];

        $this->get("http://admin.app.test/admin/tenants/{$tenant->id}/edit")->assertOk();
        Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])->assertSuccessful();
        Livewire::test(DomainsRelationManager::class, [
            'ownerRecord' => $tenant,
            'pageClass' => EditTenant::class,
        ])->assertSuccessful();
        Livewire::test(SubscriptionsRelationManager::class, [
            'ownerRecord' => $tenant,
            'pageClass' => EditTenant::class,
        ])->assertSuccessful();
        Livewire::test(SubscriptionEventsRelationManager::class, [
            'ownerRecord' => $tenant,
            'pageClass' => EditTenant::class,
        ])->assertSuccessful();

        // TenantFeaturesRelationManager shows all catalog features even with zero overrides
        $component = Livewire::test(TenantFeaturesRelationManager::class, [
            'ownerRecord' => $tenant,
            'pageClass' => EditTenant::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Feature::query()->get())
            ->assertSee(FeatureKey::AdvancedReports->value)
            ->assertSee(FeatureKey::MaxUsers->value);

        // Set and clear override via relation manager actions
        $component->callTableAction('set_override', $feature, [
            'enabled' => true,
            'value' => null,
            'expires_at' => null,
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('tenant_features', [
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'enabled' => true,
        ]);

        $component->callTableAction('clear_override', $feature)->assertHasNoTableActionErrors();

        $this->assertNull(
            TenantFeature::query()
                ->where('tenant_id', $tenant->id)
                ->where('feature_id', $feature->id)
                ->first()
        );
    }
}
