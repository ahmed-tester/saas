<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionEvents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.titles.subscription_events');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->pluralModelLabel(__('admin.titles.subscription_events'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('performer.email')->label(__('admin.fields.performed_by'))->placeholder(__('admin.placeholders.system')),
                TextColumn::make('note')->limit(40),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }
}
