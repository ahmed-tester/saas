<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\ExpiryBehavior;
use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\FeatureType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'type',
        'expiry_behavior',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => FeatureKey::class,
            'type' => FeatureType::class,
            'expiry_behavior' => ExpiryBehavior::class,
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'feature_plan')
            ->using(FeaturePlan::class)
            ->withPivot(['value', 'expiry_behavior'])
            ->withTimestamps();
    }

    public function featurePlans(): HasMany
    {
        return $this->hasMany(FeaturePlan::class, 'feature_id');
    }

    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class, 'feature_id');
    }
}
