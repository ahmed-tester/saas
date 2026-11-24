<?php

declare(strict_types=1);

namespace App\Exceptions\Tenancy;

use RuntimeException;

class TenantMismatchException extends RuntimeException
{
    public static function forModel(string $modelClass, int|string|null $modelTenantId, int|string $currentTenantId): self
    {
        return new self(
            sprintf(
                'Cross-tenant write blocked for [%s]: record belongs to tenant [%s], current tenant is [%s].',
                $modelClass,
                $modelTenantId === null ? 'null' : (string) $modelTenantId,
                (string) $currentTenantId,
            )
        );
    }
}
