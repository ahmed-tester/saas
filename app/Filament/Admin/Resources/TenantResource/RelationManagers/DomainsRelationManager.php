<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Enums\Tenancy\DomainType;
use App\Models\Tenancy\Domain;
use App\Models\Tenancy\Tenant;
use App\Rules\ValidTenantSlug;
use App\Services\Tenancy\DomainService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('domain')->searchable(),
                TextColumn::make('type')->badge(),
                IconColumn::make('is_primary')->boolean()->label(__('admin.fields.primary')),
                TextColumn::make('verified_at')->dateTime()->placeholder(__('admin.placeholders.unverified')),
            ])
            ->headerActions([
                Action::make('addSubdomain')
                    ->label(__('admin.actions.add_subdomain'))
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('admin.fields.subdomain_slug'))
                            ->required()
                            ->rules([new ValidTenantSlug]),
                    ])
                    ->action(function (array $data): void {
                        /** @var Tenant $tenant */
                        $tenant = $this->getOwnerRecord();
                        app(DomainService::class)->addSubdomain($tenant, $data['slug']);
                        Notification::make()->title(__('admin.notifications.subdomain_added'))->success()->send();
                    }),
                Action::make('addCustomDomain')
                    ->label(__('admin.actions.add_custom_domain'))
                    ->schema([
                        TextInput::make('domain')
                            ->required()
                            ->maxLength(255)
                            ->unique('domains', 'domain'),
                    ])
                    ->action(function (array $data): void {
                        /** @var Tenant $tenant */
                        $tenant = $this->getOwnerRecord();
                        app(DomainService::class)->addCustomDomain($tenant, $data['domain']);
                        Notification::make()->title(__('admin.notifications.custom_domain_added'))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('markVerified')
                    ->label(__('admin.actions.mark_verified'))
                    ->color('success')
                    ->visible(fn (Domain $record): bool => $record->type === DomainType::Custom && $record->verified_at === null)
                    ->action(function (Domain $record): void {
                        app(DomainService::class)->markVerified($record);
                        Notification::make()->title(__('admin.notifications.domain_verified'))->success()->send();
                    }),
                Action::make('setPrimary')
                    ->label(__('admin.actions.set_primary'))
                    ->color('primary')
                    ->visible(fn (Domain $record): bool => ! $record->is_primary && $record->verified_at !== null)
                    ->action(function (Domain $record): void {
                        app(DomainService::class)->setPrimary($record);
                        Notification::make()->title(__('admin.notifications.primary_domain_updated'))->success()->send();
                    }),
                Action::make('deleteDomain')
                    ->label(__('admin.actions.delete'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Domain $record): bool => ! $record->is_primary)
                    ->action(function (Domain $record): void {
                        app(DomainService::class)->delete($record);
                        Notification::make()->title(__('admin.notifications.domain_deleted'))->success()->send();
                    }),
            ]);
    }
}
