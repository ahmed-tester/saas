<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\Tenancy\TenantStatus;
use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\DomainsRelationManager;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\SubscriptionEventsRelationManager;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\TenantFeaturesRelationManager;
use App\Models\Billing\Plan;
use App\Models\Tenancy\Tenant;
use App\Rules\ValidTenantSlug;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin.resources.tenant.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.tenant.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.tenant.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.fields.name'))
                ->required()
                ->maxLength(150),

            TextInput::make('slug')
                ->label(__('admin.fields.subdomain_slug'))
                ->required()
                ->rules([new ValidTenantSlug])
                ->visibleOn('create'),

            TextInput::make('owner_name')
                ->label(__('admin.fields.owner_name'))
                ->required()
                ->maxLength(255)
                ->visibleOn('create'),

            TextInput::make('owner_email')
                ->label(__('admin.fields.owner_email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->visibleOn('create'),

            Select::make('plan_id')
                ->label(__('admin.fields.initial_plan'))
                ->options(fn () => Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                ->default(fn () => Plan::query()->where('slug', 'basic')->value('id'))
                ->required()
                ->visibleOn('create'),

            Select::make('status')
                ->label(__('admin.fields.status'))
                ->options(TenantStatus::class)
                ->required()
                ->visibleOn('edit'),

            DateTimePicker::make('trial_ends_at')
                ->label(__('admin.fields.trial_ends_at'))
                ->nullable()
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                TextColumn::make('primaryDomain.domain')->label(__('admin.fields.primary_domain'))->searchable(),
                TextColumn::make('status')->label(__('admin.fields.status'))->badge(),
                TextColumn::make('latestSubscription.plan.name')->label(__('admin.fields.plan'))->placeholder(__('admin.placeholders.none')),
                TextColumn::make('trial_ends_at')->label(__('admin.fields.trial_ends_at'))->dateTime()->sortable(),
                TextColumn::make('created_at')->label(__('admin.fields.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('admin.fields.status'))->options(TenantStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
                RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
            SubscriptionsRelationManager::class,
            TenantFeaturesRelationManager::class,
            SubscriptionEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
