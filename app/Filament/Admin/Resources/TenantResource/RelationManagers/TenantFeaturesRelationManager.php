<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Enums\Billing\FeatureType;
use App\Models\Billing\Feature;
use App\Models\Billing\FeaturePlan;
use App\Models\Billing\TenantFeature;
use App\Models\Tenancy\Tenant;
use App\Services\Billing\FeatureGate;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantFeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'tenantFeatures';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.titles.features');
    }

    public function table(Table $table): Table
    {
        /** @var Tenant $tenant */
        $tenant = $this->getOwnerRecord();
        $gate = app(FeatureGate::class)->for($tenant);
        $subscription = $tenant->latestSubscription()->first();

        $planFeatures = $subscription !== null
            ? FeaturePlan::query()
                ->where('plan_id', $subscription->plan_id)
                ->get()
                ->keyBy('feature_id')
            : collect();

        return $table
            ->query(fn (): Builder => Feature::query()->orderBy('id'))
            ->columns([
                TextColumn::make('key')
                    ->label(__('admin.fields.key'))
                    ->badge()
                    ->formatStateUsing(fn (Feature $record): string => $record->key->value),
                TextColumn::make('name')
                    ->label(__('admin.fields.name')),
                TextColumn::make('plan_inclusion')
                    ->label(__('admin.fields.plan_inclusion'))
                    ->getStateUsing(function (Feature $record) use ($planFeatures): string {
                        /** @var FeaturePlan|null $fp */
                        $fp = $planFeatures->get($record->id);
                        if ($fp === null) {
                            return __('admin.placeholders.not_in_plan');
                        }

                        if ($record->type === FeatureType::Limit) {
                            $limit = $fp->value === null ? __('admin.placeholders.unlimited') : (string) $fp->value;

                            return __('admin.placeholders.in_plan')." ({$limit})";
                        }

                        return __('admin.placeholders.in_plan');
                    }),
                TextColumn::make('override')
                    ->label(__('admin.fields.override'))
                    ->getStateUsing(function (Feature $record) use ($tenant): string {
                        /** @var TenantFeature|null $override */
                        $override = TenantFeature::query()
                            ->where('tenant_id', $tenant->id)
                            ->where('feature_id', $record->id)
                            ->first();

                        if ($override === null) {
                            return __('admin.placeholders.none');
                        }

                        $status = $override->enabled
                            ? __('admin.placeholders.enabled')
                            : __('admin.placeholders.disabled');

                        if ($override->value !== null) {
                            return "{$status} ({$override->value})";
                        }

                        return $status;
                    }),
                TextColumn::make('override_expires_at')
                    ->label(__('admin.fields.expires_at'))
                    ->getStateUsing(function (Feature $record) use ($tenant): string {
                        $override = TenantFeature::query()
                            ->where('tenant_id', $tenant->id)
                            ->where('feature_id', $record->id)
                            ->first();

                        return $override?->expires_at?->toDateTimeString() ?? __('admin.placeholders.never');
                    }),
                TextColumn::make('effective')
                    ->label(__('admin.fields.effective_access'))
                    ->badge()
                    ->getStateUsing(function (Feature $record) use ($gate): string {
                        if ($gate->canWrite($record->key)) {
                            return __('admin.access.read_write');
                        }

                        if ($gate->canRead($record->key)) {
                            return __('admin.access.read_only');
                        }

                        return __('admin.access.locked');
                    }),
                TextColumn::make('effective_limit')
                    ->label(__('admin.fields.effective_limit'))
                    ->getStateUsing(function (Feature $record) use ($gate): string {
                        if ($record->type !== FeatureType::Limit) {
                            return __('admin.placeholders.none');
                        }

                        if (! $gate->canRead($record->key)) {
                            return '0';
                        }

                        $limit = $gate->limit($record->key);

                        return $limit === null ? __('admin.placeholders.unlimited') : (string) $limit;
                    }),
            ])
            ->recordActions([
                Action::make('set_override')
                    ->label(__('admin.actions.set_override'))
                    ->fillForm(function (Feature $record) use ($tenant): array {
                        $override = TenantFeature::query()
                            ->where('tenant_id', $tenant->id)
                            ->where('feature_id', $record->id)
                            ->first();

                        return [
                            'enabled' => $override?->enabled ?? true,
                            'value' => $override?->value,
                            'expires_at' => $override?->expires_at,
                        ];
                    })
                    ->schema([
                        Toggle::make('enabled')
                            ->label(__('admin.fields.override_enabled'))
                            ->required()
                            ->default(true),
                        TextInput::make('value')
                            ->label(__('admin.fields.override_limit'))
                            ->numeric()
                            ->integer()
                            ->nullable(),
                        DateTimePicker::make('expires_at')
                            ->label(__('admin.fields.expires_at'))
                            ->nullable(),
                    ])
                    ->action(function (Feature $record, array $data) use ($tenant): void {
                        TenantFeature::query()->updateOrCreate(
                            [
                                'tenant_id' => $tenant->id,
                                'feature_id' => $record->id,
                            ],
                            [
                                'enabled' => (bool) $data['enabled'],
                                'value' => $data['value'] === '' || $data['value'] === null ? null : (int) $data['value'],
                                'expires_at' => $data['expires_at'] ?? null,
                            ]
                        );

                        Notification::make()
                            ->title(__('admin.notifications.override_saved'))
                            ->success()
                            ->send();
                    }),
                Action::make('clear_override')
                    ->label(__('admin.actions.clear_override'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(function (Feature $record) use ($tenant): bool {
                        return TenantFeature::query()
                            ->where('tenant_id', $tenant->id)
                            ->where('feature_id', $record->id)
                            ->exists();
                    })
                    ->action(function (Feature $record) use ($tenant): void {
                        TenantFeature::query()
                            ->where('tenant_id', $tenant->id)
                            ->where('feature_id', $record->id)
                            ->delete();

                        Notification::make()
                            ->title(__('admin.notifications.override_cleared'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
