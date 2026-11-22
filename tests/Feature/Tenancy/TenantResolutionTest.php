<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Admin\SuperAdmin;
use App\Models\Tenancy\Domain;
use App\Models\Tenancy\Tenant;
use App\Services\Tenancy\DomainService;
use App\Services\Tenancy\DomainTenantResolver;
use App\Services\Tenancy\TenantProvisioner;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.base_domain', 'app.test');
        config()->set('app.admin_domain', 'admin.app.test');
        config()->set('tenancy.central_domains', ['admin.app.test', 'app.test']);

        $this->seed([FeatureSeeder::class, PlanSeeder::class, PermissionSeeder::class]);
    }

    public function test_known_subdomain_resolves_to_correct_tenant(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Shop One',
            'slug' => 'shop1',
            'owner_name' => 'Owner One',
            'owner_email' => 'owner1@example.com',
        ]);

        $response = $this->get('http://shop1.app.test/login');

        $response->assertOk();
        $response->assertSee('Shop One');
        $this->assertSame($provisioned['tenant']->id, tenant()?->getTenantKey());
    }

    public function test_unknown_subdomain_returns_404(): void
    {
        $this->get('http://unknown.app.test/login')->assertNotFound();
    }

    public function test_verified_custom_domain_resolves_and_unverified_returns_404(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Custom Shop',
            'slug' => 'custom1',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@custom.test',
        ]);

        /** @var Tenant $tenant */
        $tenant = $provisioned['tenant'];
        $domainService = app(DomainService::class);

        /** @var Domain $customDomain */
        $customDomain = $domainService->addCustomDomain($tenant, 'shop.client.test');

        $this->get('http://shop.client.test/login')->assertNotFound();

        $domainService->markVerified($customDomain);

        $this->get('http://shop.client.test/login')->assertOk();
    }

    public function test_unknown_host_returns_404(): void
    {
        $this->get('http://stranger.example.org/login')->assertNotFound();
    }

    public function test_suspended_tenant_returns_403_on_subdomain_and_custom_domain(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Suspended Shop',
            'slug' => 'susp1',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@susp.test',
        ]);

        /** @var Tenant $tenant */
        $tenant = $provisioned['tenant'];
        $domainService = app(DomainService::class);
        $custom = $domainService->addCustomDomain($tenant, 'susp.client.test');
        $domainService->markVerified($custom);

        $tenant->update(['status' => TenantStatus::Suspended]);

        $this->get('http://susp1.app.test/login')->assertForbidden();
        $this->get('http://susp.client.test/login')->assertForbidden();
    }

    public function test_central_domain_does_not_initialize_tenant_and_admin_works(): void
    {
        SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin@app.test',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->assertNull(app(DomainTenantResolver::class)->resolveByHost('admin.app.test'));
        $this->get('http://admin.app.test/login')->assertNotFound();
        $this->get('http://admin.app.test/admin/login')->assertOk();
    }

    public function test_host_with_uppercase_and_port_is_normalized(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Upper Shop',
            'slug' => 'upper1',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@upper.test',
        ]);

        $resolved = app(DomainTenantResolver::class)->resolveByHost('UPPER1.APP.TEST:8080');

        $this->assertNotNull($resolved);
        $this->assertSame($provisioned['tenant']->id, $resolved->id);
    }
}
