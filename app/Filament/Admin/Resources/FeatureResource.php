<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\Billing\ExpiryBehavior;
use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\FeatureType;
use App\Filament\Admin\Resources\FeatureResource\Pages;
use App\Models\Billing\Feature;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.resources.feature.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.feature.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.feature.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('key')
                ->label(__('admin.fields.key'))
                ->options(collect(FeatureKey::cases())->mapWithKeys(fn (FeatureKey $k): array => [$k->value => $k->value]))
                ->required()
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Feature $record): bool => $record !== null),
            TextInput::make('name')
                ->label(__('admin.fields.name'))
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label(__('admin.fields.type'))
                ->options(FeatureType::class)
                ->required(),
            Select::make('expiry_behavior')
                ->label(__('admin.fields.expiry_behavior'))
                ->options(ExpiryBehavior::class)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label(__('admin.fields.key'))->badge()->searchable(),
                TextColumn::make('name')->label(__('admin.fields.name'))->searchable(),
                TextColumn::make('type')->label(__('admin.fields.type'))->badge(),
                TextColumn::make('expiry_behavior')->label(__('admin.fields.expiry_behavior'))->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Feature $record): bool => ! $record->featurePlans()->exists() && ! $record->tenantFeatures()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit' => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
