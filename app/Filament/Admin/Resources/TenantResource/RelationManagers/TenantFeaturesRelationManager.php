<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Models\Billing\TenantFeature;
use App\Models\Tenancy\Tenant;
use App\Services\Billing\FeatureGate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TenantFeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'tenantFeatures';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.titles.feature_overrides');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('feature_id')
                ->relationship('feature', 'name')
                ->required()
                ->disabled(fn (?TenantFeature $record): bool => $record !== null),
            Toggle::make('enabled')
                ->required()
                ->default(true),
            TextInput::make('value')
                ->numeric()
                ->integer()
                ->nullable(),
            DateTimePicker::make('expires_at')
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Tenant $tenant */
        $tenant = $this->getOwnerRecord();
        $gate = app(FeatureGate::class)->for($tenant);

        return $table
            ->columns([
                TextColumn::make('feature.key')->label(__('admin.fields.plan'))->badge(),
                IconColumn::make('enabled')->boolean()->label(__('admin.fields.override_enabled')),
                TextColumn::make('value')->label(__('admin.fields.override_limit'))->placeholder(__('admin.placeholders.default')),
                TextColumn::make('expires_at')->dateTime()->placeholder(__('admin.placeholders.never')),
                TextColumn::make('effective')
                    ->label(__('admin.fields.effective_access'))
                    ->getStateUsing(function (TenantFeature $record) use ($gate): string {
                        $key = $record->feature->key;

                        if ($gate->canWrite($key)) {
                            return __('admin.access.read_write');
                        }

                        if ($gate->canRead($key)) {
                            return __('admin.access.read_only');
                        }

                        return __('admin.access.locked');
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
