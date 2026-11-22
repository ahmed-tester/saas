<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Admin\Widgets\AdminStatsOverview;
use App\Filament\Admin\Widgets\ExpiringSubscriptionsWidget;
use App\Filament\Admin\Widgets\RecentSubscriptionEventsWidget;
use App\Models\Admin\SuperAdmin;
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
}
