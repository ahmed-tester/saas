<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\Tenancy\TenantContextMissingException;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant as BaseBelongsToTenant;

/**
 * Wraps stancl/tenancy's BelongsToTenant trait with fail-closed behavior
 * when no tenant context is active (outside explicit TenantContext::central()).
 */
trait BelongsToTenant
{
    use BaseBelongsToTenant {
        bootBelongsToTenant as protected bootBaseBelongsToTenant;
    }

    public static function bootBelongsToTenant(): void
    {
        static::bootBaseBelongsToTenant();

        static::addGlobalScope('require_tenant_context', static function (Builder $builder): void {
            if (! tenancy()->initialized && ! TenantContext::isCentral()) {
                throw TenantContextMissingException::forModel(static::class);
            }
        });

        static::saving(static function (Model $model): void {
            if (! tenancy()->initialized && ! TenantContext::isCentral()) {
                throw TenantContextMissingException::forModel(static::class);
            }

            if (tenancy()->initialized && ! $model->exists) {
                $model->setAttribute(BaseBelongsToTenant::$tenantIdColumn, tenant()->getTenantKey());
            }
        });

        static::deleting(static function (): void {
            if (! tenancy()->initialized && ! TenantContext::isCentral()) {
                throw TenantContextMissingException::forModel(static::class);
            }
        });
    }
}
