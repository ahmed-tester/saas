<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\Billing\SubscriptionEventType;
use App\Filament\Admin\Resources\TenantResource;
use App\Models\Billing\SubscriptionEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentSubscriptionEventsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.widgets.recent_subscription_events_title'))
            ->query(
                SubscriptionEvent::query()
                    ->with(['subscription.tenant', 'performer'])
                    ->latest('id')
            )
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('subscription.tenant.name')
                    ->label(__('admin.widgets.tenant'))
                    ->url(fn (SubscriptionEvent $record): ?string => $record->subscription?->tenant_id
                        ? TenantResource::getUrl('edit', ['record' => $record->subscription->tenant_id])
                        : null)
                    ->placeholder(__('admin.placeholders.none')),

                TextColumn::make('type')
                    ->label(__('admin.widgets.event_type'))
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionEventType $state): string => $state->label())
                    ->color(fn (SubscriptionEventType $state): string => match ($state) {
                        SubscriptionEventType::Activated, SubscriptionEventType::Renewed => 'success',
                        SubscriptionEventType::PlanChanged, SubscriptionEventType::Extended => 'info',
                        SubscriptionEventType::Canceled => 'danger',
                    }),

                TextColumn::make('performer.email')
                    ->label(__('admin.fields.performed_by'))
                    ->placeholder(__('admin.placeholders.system')),

                TextColumn::make('note')
                    ->label(__('admin.fields.note'))
                    ->limit(40)
                    ->placeholder(__('admin.placeholders.none')),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->emptyStateHeading(__('admin.widgets.no_recent_subscription_events'));
    }
}
