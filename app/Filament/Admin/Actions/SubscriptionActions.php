<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Plan;
use App\Models\Tenancy\Tenant;
use App\Services\Billing\SubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

class SubscriptionActions
{
    public static function activate(Tenant $tenant): Action
    {
        return Action::make('activateSubscription')
            ->label(__('admin.actions.activate'))
            ->color('success')
            ->visible(fn (): bool => $tenant->latestSubscription()->first()?->status !== SubscriptionStatus::Active)
            ->schema([
                Select::make('plan_id')
                    ->label(__('admin.fields.plan'))
                    ->options(fn () => Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->default(fn () => $tenant->latestSubscription()->first()?->plan_id)
                    ->required(),
                TextInput::make('grace_days')->label(__('admin.fields.grace_days'))->numeric()->integer()->nullable(),
                TextInput::make('custom_price')->label(__('admin.fields.custom_price'))->numeric()->nullable(),
                Toggle::make('endless')->label(__('admin.fields.no_end_date')),
                Textarea::make('payment_note')->label(__('admin.fields.payment_note'))->nullable(),
            ])
            ->action(function (array $data) use ($tenant): void {
                $plan = Plan::query()->findOrFail($data['plan_id']);

                app(SubscriptionService::class)->activate(
                    tenant: $tenant,
                    plan: $plan,
                    graceDays: isset($data['grace_days']) ? (int) $data['grace_days'] : null,
                    customPrice: isset($data['custom_price']) ? (string) $data['custom_price'] : null,
                    paymentNote: $data['payment_note'] ?? null,
                    endless: (bool) ($data['endless'] ?? false),
                );

                Notification::make()->title(__('admin.notifications.subscription_activated'))->success()->send();
            });
    }

    public static function renew(Tenant $tenant): Action
    {
        return Action::make('renewSubscription')
            ->label(__('admin.actions.renew'))
            ->color('primary')
            ->visible(function () use ($tenant): bool {
                $latest = $tenant->latestSubscription()->first();

                return $latest !== null && $latest->status === SubscriptionStatus::Active && $latest->ends_at !== null;
            })
            ->schema([
                Textarea::make('payment_note')->label(__('admin.fields.payment_note'))->nullable(),
            ])
            ->action(function (array $data) use ($tenant): void {
                app(SubscriptionService::class)->renew(
                    tenant: $tenant,
                    paymentNote: $data['payment_note'] ?? null,
                );

                Notification::make()->title(__('admin.notifications.subscription_renewed'))->success()->send();
            });
    }

    public static function changePlan(Tenant $tenant): Action
    {
        return Action::make('changePlan')
            ->label(__('admin.actions.change_plan'))
            ->color('warning')
            ->schema([
                Select::make('plan_id')
                    ->label(__('admin.fields.new_plan'))
                    ->options(fn () => Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->required(),
                TextInput::make('grace_days')->label(__('admin.fields.grace_days'))->numeric()->integer()->nullable(),
                TextInput::make('custom_price')->label(__('admin.fields.custom_price'))->numeric()->nullable(),
                Toggle::make('endless')->label(__('admin.fields.no_end_date')),
                Textarea::make('payment_note')->label(__('admin.fields.payment_note'))->nullable(),
            ])
            ->action(function (array $data) use ($tenant): void {
                $plan = Plan::query()->findOrFail($data['plan_id']);

                app(SubscriptionService::class)->changePlan(
                    tenant: $tenant,
                    newPlan: $plan,
                    graceDays: isset($data['grace_days']) ? (int) $data['grace_days'] : null,
                    customPrice: isset($data['custom_price']) ? (string) $data['custom_price'] : null,
                    paymentNote: $data['payment_note'] ?? null,
                    endless: (bool) ($data['endless'] ?? false),
                );

                Notification::make()->title(__('admin.notifications.plan_changed'))->success()->send();
            });
    }

    public static function extend(Tenant $tenant): Action
    {
        return Action::make('extendSubscription')
            ->label(__('admin.actions.extend'))
            ->color('info')
            ->visible(function () use ($tenant): bool {
                $latest = $tenant->latestSubscription()->first();

                return $latest !== null
                    && $latest->status !== SubscriptionStatus::Canceled
                    && $latest->ends_at !== null;
            })
            ->schema([
                TextInput::make('days')
                    ->label(__('admin.fields.days'))
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Textarea::make('note')->label(__('admin.fields.note'))->nullable(),
            ])
            ->action(function (array $data) use ($tenant): void {
                app(SubscriptionService::class)->extend(
                    tenant: $tenant,
                    days: (int) $data['days'],
                    note: $data['note'] ?? null,
                );

                Notification::make()->title(__('admin.notifications.subscription_extended'))->success()->send();
            });
    }

    public static function cancel(Tenant $tenant): Action
    {
        return Action::make('cancelSubscription')
            ->label(__('admin.actions.cancel'))
            ->color('danger')
            ->requiresConfirmation()
            ->visible(function () use ($tenant): bool {
                $latest = $tenant->latestSubscription()->first();

                return $latest !== null && $latest->status !== SubscriptionStatus::Canceled;
            })
            ->schema([
                Textarea::make('note')->label(__('admin.fields.note'))->nullable(),
            ])
            ->action(function (array $data) use ($tenant): void {
                app(SubscriptionService::class)->cancel(
                    tenant: $tenant,
                    note: $data['note'] ?? null,
                );

                Notification::make()->title(__('admin.notifications.subscription_canceled'))->success()->send();
            });
    }
}
