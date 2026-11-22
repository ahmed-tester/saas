<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\ExpiryBehavior;
use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\SubscriptionState;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Tenancy\TenantStatus;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\InitializeTenancyByCustomDomain;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Tenancy\Tenant;
use App\Services\Billing\FeatureGate;
use App\Services\Tenancy\DomainService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private Plan $basic;

    private Plan $pro;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.base_domain', 'app.test');
        config()->set('app.admin_domain', 'admin.app.test');
        config()->set('tenancy.central_domains', ['admin.app.test', 'app.test']);

        $this->seed([FeatureSeeder::class, PlanSeeder::class, PermissionSeeder::class]);

        $this->basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $this->pro = Plan::query()->where('slug', 'pro')->firstOrFail();
    }

    public function test_feature_gate_12_case_matrix(): void
    {
        $gate = app(FeatureGate::class);

        // 12. State: none (even with enabled override)
        $noneTenant = Tenant::query()->create(['name' => 'None', 'status' => TenantStatus::Trial]);
        $invoicingFeature = Feature::query()->where('key', FeatureKey::Invoicing)->firstOrFail();
        $noneTenant->tenantFeatures()->create(['feature_id' => $invoicingFeature->id, 'enabled' => true]);

        $this->assertSame(SubscriptionState::None, $gate->for($noneTenant)->state());
        $this->assertFalse($gate->for($noneTenant)->canRead(FeatureKey::Invoicing));
        $this->assertFalse($gate->for($noneTenant)->canWrite(FeatureKey::Invoicing));

        // 1-4. Active subscription (Basic plan: invoicing=yes, multi_warehouse=no)
        $activeTenant = Tenant::query()->create(['name' => 'Active', 'status' => TenantStatus::Active]);
        $activeTenant->subscriptions()->create([
            'plan_id' => $this->basic->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        // 1. In plan, no override
        $this->assertTrue($gate->for($activeTenant)->canRead(FeatureKey::Invoicing));
        $this->assertTrue($gate->for($activeTenant)->canWrite(FeatureKey::Invoicing));

        // 2. Outside plan, no override
        $this->assertFalse($gate->for($activeTenant)->canRead(FeatureKey::MultiWarehouse));
        $this->assertFalse($gate->for($activeTenant)->canWrite(FeatureKey::MultiWarehouse));

        // 3. Outside plan, enabled override
        $multiFeature = Feature::query()->where('key', FeatureKey::MultiWarehouse)->firstOrFail();
        $activeTenant->tenantFeatures()->create(['feature_id' => $multiFeature->id, 'enabled' => true]);
        $this->assertTrue($gate->for($activeTenant)->canRead(FeatureKey::MultiWarehouse));
        $this->assertTrue($gate->for($activeTenant)->canWrite(FeatureKey::MultiWarehouse));

        // 4. In plan, disabled override
        $activeTenant->tenantFeatures()->create(['feature_id' => $invoicingFeature->id, 'enabled' => false]);
        $this->assertFalse($gate->for($activeTenant)->canRead(FeatureKey::Invoicing));
        $this->assertFalse($gate->for($activeTenant)->canWrite(FeatureKey::Invoicing));

        // 5. Grace period
        $graceTenant = Tenant::query()->create(['name' => 'Grace', 'status' => TenantStatus::Active]);
        $graceTenant->subscriptions()->create([
            'plan_id' => $this->basic->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDays(2),
            'grace_days' => 7,
        ]);
        $this->assertSame(SubscriptionState::Grace, $gate->for($graceTenant)->state());
        $this->assertTrue($gate->for($graceTenant)->canRead(FeatureKey::Invoicing));
        $this->assertTrue($gate->for($graceTenant)->canWrite(FeatureKey::Invoicing));

        // 6-11. Expired subscription (Pro plan has data_export=keep, invoicing=read_only, barcode_printing=lock)
        $expiredTenant = Tenant::query()->create(['name' => 'Expired', 'status' => TenantStatus::Active]);
        $expiredTenant->subscriptions()->create([
            'plan_id' => $this->pro->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(20),
            'grace_days' => 7,
        ]);
        $this->assertSame(SubscriptionState::Expired, $gate->for($expiredTenant)->state());

        // 6. Expired, keep
        $this->assertTrue($gate->for($expiredTenant)->canRead(FeatureKey::DataExport));
        $this->assertTrue($gate->for($expiredTenant)->canWrite(FeatureKey::DataExport));

        // 7. Expired, read_only
        $this->assertTrue($gate->for($expiredTenant)->canRead(FeatureKey::Invoicing));
        $this->assertFalse($gate->for($expiredTenant)->canWrite(FeatureKey::Invoicing));

        // 8. Expired, lock
        $this->assertFalse($gate->for($expiredTenant)->canRead(FeatureKey::BarcodePrinting));
        $this->assertFalse($gate->for($expiredTenant)->canWrite(FeatureKey::BarcodePrinting));

        // 9. Expired, outside plan (test with basic plan + multi_warehouse)
        $expiredBasic = Tenant::query()->create(['name' => 'ExpiredBasic', 'status' => TenantStatus::Active]);
        $expiredBasic->subscriptions()->create([
            'plan_id' => $this->basic->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(20),
            'grace_days' => 7,
        ]);
        $this->assertFalse($gate->for($expiredBasic)->canRead(FeatureKey::MultiWarehouse));
        $this->assertFalse($gate->for($expiredBasic)->canWrite(FeatureKey::MultiWarehouse));

        // 10. Expired lock with non-expired enabled override
        $barcodeFeature = Feature::query()->where('key', FeatureKey::BarcodePrinting)->firstOrFail();
        $override = $expiredTenant->tenantFeatures()->create([
            'feature_id' => $barcodeFeature->id,
            'enabled' => true,
            'expires_at' => now()->addDays(5),
        ]);
        $this->assertTrue($gate->for($expiredTenant)->canRead(FeatureKey::BarcodePrinting));
        $this->assertTrue($gate->for($expiredTenant)->canWrite(FeatureKey::BarcodePrinting));

        // 11. Expired lock with expired override
        $override->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($gate->for($expiredTenant)->canRead(FeatureKey::BarcodePrinting));
        $this->assertFalse($gate->for($expiredTenant)->canWrite(FeatureKey::BarcodePrinting));
    }

    public function test_feature_plan_expiry_behavior_overrides_feature_default_and_limit_rules(): void
    {
        $gate = app(FeatureGate::class);
        $barcodeFeature = Feature::query()->where('key', FeatureKey::BarcodePrinting)->firstOrFail();
        $maxUsersFeature = Feature::query()->where('key', FeatureKey::MaxUsers)->firstOrFail();

        // Override expiry_behavior on basic plan for barcode_printing from lock -> read_only
        $this->basic->features()->attach($barcodeFeature->id, [
            'value' => null,
            'expiry_behavior' => ExpiryBehavior::ReadOnly->value,
        ]);

        $tenant = Tenant::query()->create(['name' => 'LimitTest', 'status' => TenantStatus::Active]);
        $tenant->subscriptions()->create([
            'plan_id' => $this->basic->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(20),
            'grace_days' => 7,
        ]);

        $this->assertTrue($gate->for($tenant)->canRead(FeatureKey::BarcodePrinting));
        $this->assertFalse($gate->for($tenant)->canWrite(FeatureKey::BarcodePrinting));

        // Limit: basic max_users = 3, override = 10, pro max_products = null (unlimited), locked = 0
        $this->assertSame(3, $gate->for($tenant)->limit(FeatureKey::MaxUsers));
        $tenant->tenantFeatures()->create([
            'feature_id' => $maxUsersFeature->id,
            'enabled' => true,
            'value' => 10,
        ]);
        $this->assertSame(10, $gate->for($tenant)->limit(FeatureKey::MaxUsers));
        $this->assertSame(0, $gate->for($tenant)->limit(FeatureKey::MultiWarehouse));

        $proTenant = Tenant::query()->create(['name' => 'ProLimit', 'status' => TenantStatus::Active]);
        $proTenant->subscriptions()->create([
            'plan_id' => $this->pro->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $this->assertNull($gate->for($proTenant)->limit(FeatureKey::MaxProducts));
    }

    public function test_ensure_feature_middleware_allows_get_and_blocks_post_on_read_only(): void
    {
        Route::middleware([
            'web',
            InitializeTenancyByCustomDomain::class,
            EnsureFeature::class.':invoicing',
        ])->group(function (): void {
            Route::get('/_test/invoices', fn () => response()->json(['ok' => true]));
            Route::post('/_test/invoices', fn () => response()->json(['ok' => true]));
        });

        $tenant = Tenant::query()->create(['name' => 'MW', 'status' => TenantStatus::Active]);
        app(DomainService::class)->addSubdomain($tenant, 'mwtest', isPrimary: true);
        $tenant->subscriptions()->create([
            'plan_id' => $this->basic->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(20),
            'grace_days' => 7,
        ]);

        $this->getJson('http://mwtest.app.test/_test/invoices')->assertOk();
        $this->postJson('http://mwtest.app.test/_test/invoices')
            ->assertForbidden()
            ->assertJson(['code' => 'feature_read_only']);
    }
}
