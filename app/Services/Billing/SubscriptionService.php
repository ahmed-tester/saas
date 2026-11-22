<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingPeriod;
use App\Enums\Billing\SubscriptionEventType;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Settings\SettingKey;
use App\Models\Admin\SuperAdmin;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Tenancy\Tenant;
use App\Services\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionService
{
    public function createTrial(
        Tenant $tenant,
        Plan $plan,
        ?SuperAdmin $actor = null,
        ?string $note = null,
    ): Subscription {
        return DB::transaction(function () use ($tenant, $plan, $actor, $note): Subscription {
            $now = now();
            $trialDays = Settings::getInt(SettingKey::TrialDays);

            /** @var Subscription $subscription */
            $subscription = $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial,
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($trialDays),
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Activated, $actor, $note, [
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial->value,
            ]);

            return $subscription;
        });
    }

    public function activate(
        Tenant $tenant,
        Plan $plan,
        ?SuperAdmin $actor = null,
        ?int $graceDays = null,
        ?string $customPrice = null,
        ?string $paymentNote = null,
        bool $endless = false,
        ?CarbonInterface $startsAt = null,
    ): Subscription {
        $start = $startsAt ?? now();

        if ($start->isFuture()) {
            throw new InvalidArgumentException(__('billing.invalid_starts_at'));
        }

        return DB::transaction(function () use (
            $tenant,
            $plan,
            $actor,
            $graceDays,
            $customPrice,
            $paymentNote,
            $endless,
            $start,
        ): Subscription {
            $latest = $tenant->latestSubscription()->first();
            $endsAt = $endless ? null : $this->addBillingPeriod($start, $plan->billing_period);

            if ($latest !== null && $latest->status === SubscriptionStatus::Active) {
                throw new InvalidArgumentException(__('billing.cannot_activate_active'));
            }

            if ($latest !== null && $latest->status === SubscriptionStatus::Trial) {
                $latest->update([
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => $start,
                    'ends_at' => $endsAt,
                    'grace_days' => $graceDays,
                    'custom_price' => $customPrice,
                    'payment_note' => $paymentNote,
                ]);

                $subscription = $latest->refresh();
            } else {
                /** @var Subscription $subscription */
                $subscription = $tenant->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => $start,
                    'ends_at' => $endsAt,
                    'grace_days' => $graceDays,
                    'custom_price' => $customPrice,
                    'payment_note' => $paymentNote,
                ]);
            }

            $this->recordEvent($subscription, SubscriptionEventType::Activated, $actor, $paymentNote, [
                'plan_id' => $plan->id,
                'endless' => $endless,
            ]);

            return $subscription;
        });
    }

    public function renew(
        Tenant $tenant,
        ?SuperAdmin $actor = null,
        ?string $paymentNote = null,
    ): Subscription {
        return DB::transaction(function () use ($tenant, $actor, $paymentNote): Subscription {
            $subscription = $tenant->latestSubscription()->with('plan')->firstOrFail();

            if ($subscription->status !== SubscriptionStatus::Active) {
                throw new InvalidArgumentException(__('billing.cannot_renew_non_active'));
            }

            if ($subscription->ends_at === null) {
                throw new InvalidArgumentException(__('billing.cannot_renew_endless'));
            }

            $now = now();
            $baseDate = $subscription->ends_at->isFuture() ? $subscription->ends_at : $now;
            $newEndsAt = $this->addBillingPeriod($baseDate, $subscription->plan->billing_period);

            $subscription->update([
                'ends_at' => $newEndsAt,
                'payment_note' => $paymentNote ?? $subscription->payment_note,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Renewed, $actor, $paymentNote, [
                'ends_at' => $newEndsAt->toIso8601String(),
            ]);

            return $subscription->refresh();
        });
    }

    public function extend(
        Tenant $tenant,
        int $days,
        ?SuperAdmin $actor = null,
        ?string $note = null,
    ): Subscription {
        if ($days <= 0) {
            throw new InvalidArgumentException(__('billing.invalid_extend_days'));
        }

        return DB::transaction(function () use ($tenant, $days, $actor, $note): Subscription {
            $subscription = $tenant->latestSubscription()->firstOrFail();

            if ($subscription->status === SubscriptionStatus::Canceled) {
                throw new InvalidArgumentException(__('billing.cannot_extend_canceled'));
            }

            if ($subscription->ends_at === null) {
                throw new InvalidArgumentException(__('billing.cannot_extend_endless'));
            }

            $newEndsAt = $subscription->ends_at->copy()->addDays($days);

            $subscription->update([
                'ends_at' => $newEndsAt,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Extended, $actor, $note, [
                'days' => $days,
                'ends_at' => $newEndsAt->toIso8601String(),
            ]);

            return $subscription->refresh();
        });
    }

    public function changePlan(
        Tenant $tenant,
        Plan $newPlan,
        ?SuperAdmin $actor = null,
        ?int $graceDays = null,
        ?string $customPrice = null,
        ?string $paymentNote = null,
        bool $endless = false,
        ?CarbonInterface $startsAt = null,
    ): Subscription {
        $start = $startsAt ?? now();

        if ($start->isFuture()) {
            throw new InvalidArgumentException(__('billing.invalid_starts_at'));
        }

        return DB::transaction(function () use (
            $tenant,
            $newPlan,
            $actor,
            $graceDays,
            $customPrice,
            $paymentNote,
            $endless,
            $start,
        ): Subscription {
            $previous = $tenant->latestSubscription()->first();

            /** @var Subscription $subscription */
            $subscription = $tenant->subscriptions()->create([
                'plan_id' => $newPlan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $start,
                'ends_at' => $endless ? null : $this->addBillingPeriod($start, $newPlan->billing_period),
                'grace_days' => $graceDays,
                'custom_price' => $customPrice,
                'payment_note' => $paymentNote,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::PlanChanged, $actor, $paymentNote, [
                'previous_plan_id' => $previous?->plan_id,
                'new_plan_id' => $newPlan->id,
            ]);

            return $subscription;
        });
    }

    public function cancel(
        Tenant $tenant,
        ?SuperAdmin $actor = null,
        ?string $note = null,
    ): Subscription {
        return DB::transaction(function () use ($tenant, $actor, $note): Subscription {
            $subscription = $tenant->latestSubscription()->firstOrFail();

            $subscription->update([
                'status' => SubscriptionStatus::Canceled,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Canceled, $actor, $note);

            return $subscription->refresh();
        });
    }

    private function addBillingPeriod(CarbonInterface $from, BillingPeriod $period): CarbonInterface
    {
        return match ($period) {
            BillingPeriod::Monthly => $from->copy()->addMonth(),
            BillingPeriod::Yearly => $from->copy()->addYear(),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordEvent(
        Subscription $subscription,
        SubscriptionEventType $type,
        ?SuperAdmin $actor,
        ?string $note,
        array $meta = [],
    ): void {
        $performedBy = $actor?->id ?? Auth::guard('admin')->id();

        $subscription->events()->create([
            'type' => $type,
            'performed_by' => $performedBy,
            'note' => $note,
            'meta' => $meta !== [] ? $meta : null,
            'created_at' => now(),
        ]);
    }
}
