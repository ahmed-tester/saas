<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlanResource\RelationManagers;

use App\Enums\Billing\ExpiryBehavior;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('value')
                ->numeric()
                ->integer()
                ->nullable()
                ->helperText(__('admin.helpers.feature_plan_value')),
            Select::make('expiry_behavior')
                ->options(ExpiryBehavior::class)
                ->nullable()
                ->helperText(__('admin.helpers.feature_plan_expiry')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('key')->badge(),
                TextColumn::make('name'),
                TextColumn::make('type')->badge(),
                TextColumn::make('pivot.value')->label(__('admin.fields.limit'))->placeholder(__('admin.placeholders.unlimited')),
                TextColumn::make('pivot.expiry_behavior')->label(__('admin.fields.expiry_override'))->placeholder(__('admin.placeholders.default')),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('value')->numeric()->integer()->nullable(),
                        Select::make('expiry_behavior')->options(ExpiryBehavior::class)->nullable(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ]);
    }
}
