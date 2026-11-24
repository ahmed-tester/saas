<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Exceptions\Tenancy\TenantContextMissingException;
use App\Exceptions\Tenancy\TenantMismatchException;
use App\Http\Middleware\InitializeTenancyByCustomDomain;
use App\Models\Tenancy\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\UserService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.base_domain', 'app.test');
        config()->set('app.admin_domain', 'admin.app.test');
        config()->set('tenancy.central_domains', ['admin.app.test', 'app.test']);

        $this->seed([FeatureSeeder::class, PlanSeeder::class, PermissionSeeder::class]);

        $provisioner = app(TenantProvisioner::class);

        $a = $provisioner->provision([
            'name' => 'Shop A',
            'slug' => 'shopa',
            'owner_name' => 'Owner A',
            'owner_email' => 'shared@example.com',
            'owner_password' => 'Password123!',
        ]);

        $b = $provisioner->provision([
            'name' => 'Shop B',
            'slug' => 'shopb',
            'owner_name' => 'Owner B',
            'owner_email' => 'shared@example.com',
            'owner_password' => 'Password456!',
        ]);

        $this->tenantA = $a['tenant'];
        $this->ownerA = $a['owner'];
        $this->tenantB = $b['tenant'];
        $this->ownerB = $b['owner'];
    }

    public function test_query_without_tenant_context_throws_exception(): void
    {
        tenancy()->end();

        $this->expectException(TenantContextMissingException::class);

        User::query()->count();
    }

    public function test_tenant_a_data_is_not_visible_to_tenant_b_in_queries_find_or_route_binding(): void
    {
        Route::middleware([
            'web',
            InitializeTenancyByCustomDomain::class,
        ])->get('/_test/users/{user}', fn (User $user) => response()->json(['id' => $user->id]));

        tenancy()->initialize($this->tenantB);

        $this->assertNull(User::query()->find($this->ownerA->id));
        $this->assertSame(1, User::query()->count());

        tenancy()->end();

        $this->get("http://shopb.app.test/_test/users/{$this->ownerA->id}")->assertNotFound();
        $this->get("http://shopb.app.test/_test/users/{$this->ownerB->id}")->assertOk();
    }

    public function test_creating_model_auto_fills_tenant_id_and_ignores_fillable_tenant_id(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::query()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Staff A',
            'email' => 'staff@shopa.test',
            'password' => 'Secret123!',
        ]);

        $this->assertSame($this->tenantA->id, $user->tenant_id);
        tenancy()->end();
    }

    public function test_same_email_succeeds_across_tenants_and_fails_within_same_tenant(): void
    {
        $this->assertSame('shared@example.com', $this->ownerA->email);
        $this->assertSame('shared@example.com', $this->ownerB->email);

        tenancy()->initialize($this->tenantA);

        $this->expectException(UniqueConstraintViolationException::class);

        User::query()->create([
            'name' => 'Duplicate',
            'email' => 'shared@example.com',
            'password' => 'Secret123!',
        ]);
    }

    public function test_owner_a_cannot_login_on_tenant_b_domain_even_with_same_email(): void
    {
        $this->post('http://shopb.app.test/login', [
            'email' => 'shared@example.com',
            'password' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');

        $this->post('http://shopa.app.test/login', [
            'email' => 'shared@example.com',
            'password' => 'Password123!',
        ])->assertRedirect(route('tenant.dashboard'));
    }

    public function test_owner_is_forced_to_change_password_and_reset_restores_flag(): void
    {
        $this->post('http://shopa.app.test/login', [
            'email' => 'shared@example.com',
            'password' => 'Password123!',
        ])->assertRedirect();

        $this->get('http://shopa.app.test/')
            ->assertRedirect('http://shopa.app.test/password/change');

        $this->post('http://shopa.app.test/password/change', [
            'current_password' => 'Password123!',
            'password' => 'NewPassword789!',
            'password_confirmation' => 'NewPassword789!',
        ])->assertRedirect('http://shopa.app.test');

        $this->assertFalse($this->ownerA->fresh()->must_change_password);

        $this->get('http://shopa.app.test/')->assertOk();

        // Resetting owner A password does not affect owner B (even with same email)
        $reset = app(UserService::class)->resetOwnerPassword($this->tenantA);

        $this->assertTrue($reset['user']->must_change_password);
        $this->assertTrue(Hash::check('Password456!', $this->ownerB->fresh()->password));
    }

    public function test_dispatched_job_runs_inside_originating_tenant_context(): void
    {
        $observedTenantId = null;

        tenancy()->initialize($this->tenantA);

        Bus::dispatchSync(new class($observedTenantId)
        {
            public function __construct(public ?int &$observed) {}

            public function handle(): void
            {
                $this->observed = tenancy()->initialized ? (int) tenant()->getTenantKey() : null;
            }
        });

        $this->assertSame($this->tenantA->id, $observedTenantId);
        tenancy()->end();
    }

    public function test_saving_model_from_another_tenant_throws_mismatch_exception(): void
    {
        tenancy()->initialize($this->tenantA);
        $userA = User::query()->findOrFail($this->ownerA->id);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        try {
            $userA->name = 'Tampered Name';
            $userA->save();
            $this->fail('Expected TenantMismatchException when saving tenant A model in tenant B context.');
        } catch (TenantMismatchException) {
            // Expected
        } finally {
            tenancy()->end();
        }

        $this->assertSame('Owner A', $this->ownerA->fresh()->name);
    }

    public function test_deleting_model_from_another_tenant_throws_mismatch_exception(): void
    {
        tenancy()->initialize($this->tenantA);
        $userA = User::query()->findOrFail($this->ownerA->id);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        try {
            $userA->delete();
            $this->fail('Expected TenantMismatchException when deleting tenant A model in tenant B context.');
        } catch (TenantMismatchException) {
            // Expected
        } finally {
            tenancy()->end();
        }

        $this->assertNotNull($this->ownerA->fresh());
    }
}
