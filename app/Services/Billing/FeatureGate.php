<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\ExpiryBehavior;
use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\SubscriptionState;
use App\Enums\Billing\SubscriptionStatus;
use App\Exceptions\Tenancy\TenantContextMissingException;
use App\Models\Billing\Feature;
use App\Models\Billing\FeaturePlan;
use App\Models\Billing\Subscription;
use App\Models\Billing\TenantFeature;
use App\Models\Tenancy\Tenant;

class FeatureGate
{
    private ?Tenant $tenant = null;

    public function for(Tenant $tenant): self
    {
        $clone = clone $this;
        $clone->tenant = $tenant;

        return $clone;
    }

    public function state(): SubscriptionState
    {
        $subscription = $this->latestSubscription();

        if ($subscription === null) {
            return SubscriptionState::None;
        }

        if ($subscription->status === SubscriptionStatus::Canceled) {
            return SubscriptionState::Expired;
        }

        if ($subscription->ends_at === null || $subscription->ends_at->isFuture() || $subscription->ends_at->equalTo(now())) {
            return SubscriptionState::Active;
        }

        $graceEndsAt = $subscription->ends_at->copy()->addDays($subscription->effectiveGraceDays());

        if (now()->lte($graceEndsAt)) {
            return SubscriptionState::Grace;
        }

        return SubscriptionState::Expired;
    }

    public function canRead(FeatureKey $key): bool
    {
        return $this->resolveAccess($key)['read'];
    }

    public function canWrite(FeatureKey $key): bool
    {
        return $this->resolveAccess($key)['write'];
    }

    public function limit(FeatureKey $key): ?int
    {
        return $this->resolveAccess($key)['limit'];
    }

    /**
     * @return array{read: bool, write: bool, limit: ?int}
     */
    private function resolveAccess(FeatureKey $key): array
    {
        $state = $this->state();

        if ($state === SubscriptionState::None) {
            return ['read' => false, 'write' => false, 'limit' => 0];
        }

        $tenant = $this->resolveTenant();
        $feature = Feature::query()->where('key', $key)->first();

        if ($feature === null) {
            return ['read' => false, 'write' => false, 'limit' => 0];
        }

        $subscription = $this->latestSubscription();
        $featurePlan = $subscription !== null
            ? FeaturePlan::query()
                ->where('plan_id', $subscription->plan_id)
                ->where('feature_id', $feature->id)
                ->first()
            : null;

        /** @var TenantFeature|null $override */
        $override = TenantFeature::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->first();

        if ($override !== null && ! $override->isExpired()) {
            if (! $override->enabled) {
                return ['read' => false, 'write' => false, 'limit' => 0];
            }

            $limit = $override->value ?? $featurePlan?->value;

            return ['read' => true, 'write' => true, 'limit' => $limit];
        }

        if ($state->isSubscribed()) {
            if ($featurePlan === null) {
                return ['read' => false, 'write' => false, 'limit' => 0];
            }

            return ['read' => true, 'write' => true, 'limit' => $featurePlan->value];
        }

        // State is Expired
        if ($featurePlan === null) {
            return ['read' => false, 'write' => false, 'limit' => 0];
        }

        $behavior = $featurePlan->expiry_behavior ?? $feature->expiry_behavior;

        return match ($behavior) {
            ExpiryBehavior::Keep => ['read' => true, 'write' => true, 'limit' => $featurePlan->value],
            ExpiryBehavior::ReadOnly => ['read' => true, 'write' => false, 'limit' => $featurePlan->value],
            ExpiryBehavior::Lock => ['read' => false, 'write' => false, 'limit' => 0],
        };
    }

    private function resolveTenant(): Tenant
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }

        if (! tenancy()->initialized) {
            throw new TenantContextMissingException('FeatureGate requires an active tenant context or ->for($tenant).');
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        return $tenant;
    }

    private function latestSubscription(): ?Subscription
    {
        $tenant = $this->resolveTenant();

        return $tenant->latestSubscription()->with('plan')->first();
    }
}
