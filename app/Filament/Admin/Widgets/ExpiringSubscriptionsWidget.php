<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\Billing\SubscriptionStatus;
use App\Filament\Admin\Resources\TenantResource;
use App\Models\Billing\Subscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringSubscriptionsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.widgets.expiring_subscriptions_title'))
            ->query(
                Subscription::query()
                    ->with(['tenant', 'plan'])
                    ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '<=', now()->addDays(7))
                    ->orderBy('ends_at')
            )
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('tenant.name')
                    ->label(__('admin.widgets.tenant'))
                    ->url(fn (Subscription $record): string => TenantResource::getUrl('edit', ['record' => $record->tenant_id]))
                    ->searchable(),

                TextColumn::make('plan.name')
                    ->label(__('admin.fields.plan')),

                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => match ($state) {
                        SubscriptionStatus::Active => 'success',
                        SubscriptionStatus::Trial => 'warning',
                        SubscriptionStatus::Canceled => 'danger',
                    }),

                TextColumn::make('ends_at')
                    ->label(__('admin.widgets.ends_at'))
                    ->dateTime()
                    ->since()
                    ->color(fn (Subscription $record): string => $record->ends_at?->isPast() ? 'danger' : 'warning')
                    ->sortable(),
            ])
            ->emptyStateHeading(__('admin.widgets.no_expiring_subscriptions'));
    }
}
