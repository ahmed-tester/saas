<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Billing\SubscriptionState;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\SettingKey;
use App\Enums\Settings\SettingType;
use App\Filament\Admin\Pages\PlatformSettings;
use App\Models\Admin\SuperAdmin;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Tenancy\Tenant;
use App\Services\Billing\FeatureGate;
use App\Services\Settings\Settings;
use App\Services\Tenancy\TenantProvisioner;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            FeatureSeeder::class,
            PlanSeeder::class,
        ]);
    }

    public function test_empty_settings_table_uses_config_defaults(): void
    {
        $this->assertDatabaseCount('settings', 0);

        config()->set('billing.trial_days', 21);
        config()->set('billing.grace_days', 5);

        $this->assertSame(21, Settings::getInt(SettingKey::TrialDays));
        $this->assertSame(5, Settings::getInt(SettingKey::GraceDays));
    }

    public function test_saved_zero_grace_days_is_returned_and_applied_instead_of_config_fallback(): void
    {
        config()->set('billing.grace_days', 7);

        Settings::setInt(SettingKey::GraceDays, 0);

        $this->assertSame(0, Settings::getInt(SettingKey::GraceDays));

        $row = DB::table('settings')
            ->where('key', SettingKey::GraceDays->value)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('billing', $row->group);
        $this->assertSame('integer', $row->type);
        $this->assertSame(0, json_decode((string) $row->value, true, 512, JSON_THROW_ON_ERROR));

        $plan = Plan::query()->where('slug', 'basic')->firstOrFail();
        $tenant = Tenant::query()->create(['name' => 'Store Zero Grace']);

        $subscription = $tenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subMinute(),
            'grace_days' => null,
        ]);

        $this->assertSame(0, $subscription->effectiveGraceDays());
        $this->assertSame(SubscriptionState::Expired, app(FeatureGate::class)->for($tenant)->state());
    }

    public function test_invalid_batch_update_rolls_back_both_settings(): void
    {
        Settings::setManyInt([
            SettingKey::TrialDays->value => 10,
            SettingKey::GraceDays->value => 4,
        ]);

        try {
            Settings::setManyInt([
                SettingKey::TrialDays->value => 30,
                SettingKey::GraceDays->value => -1,
            ]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            // Expected
        }

        $this->assertSame(10, Settings::getInt(SettingKey::TrialDays));
        $this->assertSame(4, Settings::getInt(SettingKey::GraceDays));
    }

    public function test_type_mismatch_on_read_or_write_throws_exception(): void
    {
        try {
            Settings::getString(SettingKey::TrialDays);
            $this->fail('Expected InvalidArgumentException on mismatched getter.');
        } catch (InvalidArgumentException) {
            // Expected
        }

        $this->expectException(InvalidArgumentException::class);
        Settings::set(SettingKey::TrialDays, 'not-an-integer');
    }

    public function test_changing_trial_days_affects_only_new_tenants_and_syncs_end_timestamp(): void
    {
        $provisioner = app(TenantProvisioner::class);

        $first = $provisioner->provision([
            'name' => 'First Store',
            'slug' => 'first-store',
            'owner_name' => 'Owner One',
            'owner_email' => 'owner1@example.test',
        ]);

        $originalTenantEnd = $first['tenant']->fresh()->trial_ends_at;
        $originalSubEnd = $first['subscription']->fresh()->ends_at;

        $this->assertNotNull($originalTenantEnd);
        $this->assertTrue($originalTenantEnd->equalTo($originalSubEnd));

        Settings::setInt(SettingKey::TrialDays, 30);

        $second = $provisioner->provision([
            'name' => 'Second Store',
            'slug' => 'second-store',
            'owner_name' => 'Owner Two',
            'owner_email' => 'owner2@example.test',
        ]);

        $this->assertTrue($first['tenant']->fresh()->trial_ends_at->equalTo($originalTenantEnd));
        $this->assertTrue($first['subscription']->fresh()->ends_at->equalTo($originalSubEnd));

        $secondTenantEnd = $second['tenant']->fresh()->trial_ends_at;
        $secondSubEnd = $second['subscription']->fresh()->ends_at;

        $this->assertTrue($secondTenantEnd->equalTo($secondSubEnd));
        $this->assertSame(30, (int) round($second['subscription']->starts_at->diffInDays($secondSubEnd)));
    }

    public function test_provisioning_failure_rolls_back_tenant_and_subscription(): void
    {
        Settings::setInt(SettingKey::TrialDays, 20);

        $this->expectException(RuntimeException::class);

        $provisioner = app(TenantProvisioner::class);

        // Force a failure via invalid plan_id after tenant/domain/owner creation inside transaction
        try {
            $provisioner->provision([
                'name' => 'Failing Store',
                'slug' => 'failing-store',
                'owner_name' => 'Fail Owner',
                'owner_email' => 'fail@example.test',
                'plan_id' => 999999,
            ]);
        } catch (\Throwable $e) {
            $this->assertDatabaseMissing('tenants', ['name' => 'Failing Store']);
            $this->assertDatabaseCount('subscriptions', 0);

            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function test_grace_period_update_applies_to_inheriting_subscriptions_but_respects_explicit_overrides(): void
    {
        $plan = Plan::query()->where('slug', 'basic')->firstOrFail();

        $inheritingTenant = Tenant::query()->create(['name' => 'Inheriting Store']);
        $inheritingSub = $inheritingTenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(35),
            'ends_at' => now()->subDays(5),
            'grace_days' => null,
        ]);

        $explicitZeroTenant = Tenant::query()->create(['name' => 'Explicit Zero Store']);
        $explicitZeroSub = $explicitZeroTenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(35),
            'ends_at' => now()->subDays(5),
            'grace_days' => 0,
        ]);

        $explicitCustomTenant = Tenant::query()->create(['name' => 'Explicit Custom Store']);
        $explicitCustomSub = $explicitCustomTenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(35),
            'ends_at' => now()->subDays(5),
            'grace_days' => 10,
        ]);

        $gate = app(FeatureGate::class);

        // Default config = 7 days: ends_at was 5 days ago -> Grace
        $this->assertSame(SubscriptionState::Grace, $gate->for($inheritingTenant)->state());
        $this->assertSame(SubscriptionState::Expired, $gate->for($explicitZeroTenant)->state());
        $this->assertSame(SubscriptionState::Grace, $gate->for($explicitCustomTenant)->state());

        // Lower platform default to 3 days -> inheriting subscription becomes Expired on next evaluation
        Settings::setInt(SettingKey::GraceDays, 3);

        $this->assertSame(3, $inheritingSub->fresh()->effectiveGraceDays());
        $this->assertSame(0, $explicitZeroSub->fresh()->effectiveGraceDays());
        $this->assertSame(10, $explicitCustomSub->fresh()->effectiveGraceDays());

        $this->assertSame(SubscriptionState::Expired, $gate->for($inheritingTenant)->state());
        $this->assertSame(SubscriptionState::Expired, $gate->for($explicitZeroTenant)->state());
        $this->assertSame(SubscriptionState::Grace, $gate->for($explicitCustomTenant)->state());
    }

    public function test_super_admin_can_view_and_save_billing_settings_and_invalid_input_does_not_persist(): void
    {
        $admin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        $adminUrl = 'http://'.config('app.admin_domain').'/admin/platform-settings';
        $this->get($adminUrl)->assertOk();

        $trialField = PlatformSettings::stateKey(SettingKey::TrialDays->value);
        $graceField = PlatformSettings::stateKey(SettingKey::GraceDays->value);

        Livewire::test(PlatformSettings::class)
            ->assertSet("data.{$trialField}", 14)
            ->assertSet("data.{$graceField}", 7)
            ->fillForm([
                $trialField => 21,
                $graceField => 0,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(21, Settings::getInt(SettingKey::TrialDays));
        $this->assertSame(0, Settings::getInt(SettingKey::GraceDays));

        // Invalid input leaves both values unchanged
        Livewire::test(PlatformSettings::class)
            ->fillForm([
                $trialField => 0,
                $graceField => 5,
            ])
            ->call('save')
            ->assertHasFormErrors([$trialField]);

        $this->assertSame(21, Settings::getInt(SettingKey::TrialDays));
        $this->assertSame(0, Settings::getInt(SettingKey::GraceDays));
    }

    public function test_super_admin_can_add_and_update_custom_settings_in_any_group(): void
    {
        $admin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin2@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(PlatformSettings::class)
            ->callAction('add_setting', data: [
                'group' => SettingGroup::General->value,
                'key' => 'general.site_name',
                'type' => SettingType::String->value,
                'value_string' => 'My SaaS Platform',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('My SaaS Platform', Settings::getRaw('general.site_name'));

        $customField = PlatformSettings::stateKey('general.site_name');

        Livewire::test(PlatformSettings::class)
            ->assertSet("data.{$customField}", 'My SaaS Platform')
            ->fillForm([
                $customField => 'Updated SaaS Platform',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated SaaS Platform', Settings::getRaw('general.site_name'));
    }

    public function test_tenant_user_cannot_access_or_save_billing_settings(): void
    {
        $provisioned = app(TenantProvisioner::class)->provision([
            'name' => 'Store Alpha',
            'slug' => 'store-alpha',
            'owner_name' => 'Tenant User',
            'owner_email' => 'user@example.test',
        ]);

        $this->actingAs($provisioned['owner'], 'web');

        $adminUrl = 'http://'.config('app.admin_domain').'/admin/platform-settings';
        $this->get($adminUrl)->assertRedirect();

        $this->assertFalse(PlatformSettings::canAccess());

        Livewire::test(PlatformSettings::class)
            ->assertForbidden();
    }
}
