<?php

declare(strict_types=1);

namespace App\Models\Tenancy;

use App\Enums\Tenancy\DomainType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'type',
        'is_primary',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DomainType::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function fullHost(): string
    {
        if ($this->type === DomainType::Subdomain) {
            return $this->domain . '.' . config('app.base_domain');
        }

        return $this->domain;
    }
}
