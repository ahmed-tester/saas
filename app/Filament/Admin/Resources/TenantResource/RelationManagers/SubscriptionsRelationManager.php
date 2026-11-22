<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Filament\Admin\Actions\SubscriptionActions;
use App\Models\Tenancy\Tenant;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public function table(Table $table): Table
    {
        /** @var Tenant $tenant */
        $tenant = $this->getOwnerRecord();

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('plan.name')->label(__('admin.fields.plan')),
                TextColumn::make('status')->badge(),
                TextColumn::make('starts_at')->dateTime(),
                TextColumn::make('ends_at')->dateTime()->placeholder(__('admin.placeholders.endless')),
                TextColumn::make('grace_days')->placeholder(__('admin.placeholders.default')),
                TextColumn::make('custom_price')->placeholder(__('admin.placeholders.plan_price')),
                TextColumn::make('payment_note')->limit(30),
            ])
            ->headerActions([
                SubscriptionActions::activate($tenant),
                SubscriptionActions::renew($tenant),
                SubscriptionActions::changePlan($tenant),
                SubscriptionActions::extend($tenant),
                SubscriptionActions::cancel($tenant),
            ]);
    }
}
