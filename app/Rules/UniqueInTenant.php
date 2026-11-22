<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\Tenancy\TenantContextMissingException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueInTenant implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly ?string $column = null,
        private readonly ?int $ignoreId = null,
        private readonly ?int $tenantId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenantId = $this->tenantId ?? (tenancy()->initialized ? (int) tenant()->getTenantKey() : null);

        if ($tenantId === null) {
            throw new TenantContextMissingException("Cannot validate uniqueness on [{$this->table}] without a tenant context.");
        }

        $column = $this->column ?? $attribute;

        $exists = DB::table($this->table)
            ->where('tenant_id', $tenantId)
            ->where($column, $value)
            ->when($this->ignoreId !== null, fn ($q) => $q->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($exists) {
            $fail(__('validation.unique', ['attribute' => $attribute]));
        }
    }
}
