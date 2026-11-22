<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\SubscriptionEventType;
use App\Enums\Billing\SubscriptionState;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Admin\SuperAdmin;
use App\Models\Billing\Plan;
use App\Models\Tenancy\Tenant;
use App\Services\Billing\FeatureGate;
use App\Services\Billing\SubscriptionService;
use App\Services\Tenancy\TenantProvisioner;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    private Plan $basic;

    private Plan $pro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FeatureSeeder::class, PlanSeeder::class, PermissionSeeder::class]);

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin@sub.test',
            'password' => 'Secret123!',
            'is_active' => true,
        ]);

        $this->basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $this->pro = Plan::query()->where('slug', 'pro')->firstOrFail();
    }

    public function test_provisioner_rolls_back_everything_on_failure(): void
    {
        try {
            app(TenantProvisioner::class)->provision([
                'name' => 'Broken',
                'slug' => 'broken1',
                'owner_name' => 'Owner',
                'owner_email' => 'owner@broken.test',
                'plan_id' => 999999,
            ], $this->admin);
            $this->fail('Expected exception due to missing plan.');
        } catch (\Throwable) {
            $this->assertSame(0, Tenant::query()->count());
        }
    }

    public function test_subscription_lifecycle_events_and_rules(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Shop Sub',
            'slug' => 'shopsub',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@sub.test',
        ], $this->admin);

        /** @var Tenant $tenant */
        $tenant = $provisioned['tenant'];
        $trialSub = $provisioned['subscription'];
        $service = app(SubscriptionService::class);

        $this->assertSame(SubscriptionStatus::Trial, $trialSub->status);
        $this->assertSame(1, $tenant->subscriptionEvents()->count());

        // Activate converts trial row to active in the same row
        $activated = $service->activate($tenant, $this->basic, $this->admin, paymentNote: 'INV-1');
        $this->assertSame($trialSub->id, $activated->id);
        $this->assertSame(SubscriptionStatus::Active, $activated->status);
        $this->assertSame(SubscriptionEventType::Activated, $tenant->subscriptionEvents()->latest('id')->first()->type);

        // Renew before expiry adds to ends_at
        $originalEnd = $activated->ends_at->copy();
        $renewed = $service->renew($tenant, $this->admin);
        $this->assertTrue($renewed->ends_at->equalTo($originalEnd->copy()->addMonth()));

        // Extend adds days keeping status
        $extended = $service->extend($tenant, 10, $this->admin);
        $this->assertTrue($extended->ends_at->equalTo($renewed->ends_at->copy()->addDays(10)));
        $this->assertSame(SubscriptionStatus::Active, $extended->status);

        // Cancel does NOT fall back to older subscription
        $changed = $service->changePlan($tenant, $this->pro, $this->admin);
        $this->assertNotSame($activated->id, $changed->id);
        $service->cancel($tenant, $this->admin);
        $this->assertSame(SubscriptionState::Expired, app(FeatureGate::class)->for($tenant)->state());

        // Activate after cancel creates a new row
        $reactivated = $service->activate($tenant, $this->pro, $this->admin, endless: true);
        $this->assertNotSame($changed->id, $reactivated->id);
        $this->assertNull($reactivated->ends_at);

        // Endless subscription cannot be renewed
        $this->expectException(InvalidArgumentException::class);
        $service->renew($tenant, $this->admin);
    }

    public function test_future_starts_at_is_rejected(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Future Shop',
            'slug' => 'future1',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@future.test',
        ], $this->admin);

        $this->expectException(InvalidArgumentException::class);

        app(SubscriptionService::class)->changePlan(
            $provisioned['tenant'],
            $this->pro,
            $this->admin,
            startsAt: now()->addDay(),
        );
    }
}
