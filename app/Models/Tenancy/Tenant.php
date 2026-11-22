<?php

declare(strict_types=1);

namespace App\Models\Tenancy;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Models\Billing\TenantFeature;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasDomains;
    use SoftDeletes;

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'status',
        'trial_ends_at',
        'settings',
    ];

    /**
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'status',
            'trial_ends_at',
            'settings',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(Domain::class, 'tenant_id')->where('is_primary', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id')->ofMany([
            'starts_at' => 'max',
            'id' => 'max',
        ]);
    }

    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class, 'tenant_id');
    }

    public function subscriptionEvents(): HasManyThrough
    {
        return $this->hasManyThrough(
            SubscriptionEvent::class,
            Subscription::class,
            'tenant_id',
            'subscription_id',
        );
    }
}
