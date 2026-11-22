<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\ExpiryBehavior;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FeaturePlan extends Pivot
{
    protected $table = 'feature_plan';

    public $incrementing = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'feature_id',
        'value',
        'expiry_behavior',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'expiry_behavior' => ExpiryBehavior::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }
}
