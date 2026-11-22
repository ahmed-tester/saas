<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Settings\SettingKey;
use App\Models\Tenancy\Tenant;
use App\Services\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'grace_days',
        'custom_price',
        'payment_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'grace_days' => 'integer',
            'custom_price' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class, 'subscription_id');
    }

    public function effectiveGraceDays(): int
    {
        return $this->grace_days ?? Settings::getInt(SettingKey::GraceDays);
    }
}
