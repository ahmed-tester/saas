<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\Billing\BillingPeriod;
use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Filament\Admin\Resources\PlanResource\RelationManagers\FeaturesRelationManager;
use App\Models\Billing\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.resources.plan.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.plan.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.plan.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label(__('admin.fields.slug'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Plan $record): bool => $record !== null),
            TextInput::make('name')
                ->label(__('admin.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('price')
                ->label(__('admin.fields.price'))
                ->numeric()
                ->required()
                ->default('0.00'),
            Select::make('billing_period')
                ->label(__('admin.fields.billing_period'))
                ->options(BillingPeriod::class)
                ->required(),
            TextInput::make('sort_order')
                ->label(__('admin.fields.sort_order'))
                ->numeric()
                ->integer()
                ->default(0)
                ->required(),
            Toggle::make('is_active')
                ->label(__('admin.fields.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('slug')->label(__('admin.fields.slug'))->badge()->searchable(),
                TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                TextColumn::make('price')->label(__('admin.fields.price'))->sortable(),
                TextColumn::make('billing_period')->label(__('admin.fields.billing_period'))->badge(),
                IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                TextColumn::make('sort_order')->label(__('admin.fields.sort_order'))->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
