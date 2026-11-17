<?php

declare(strict_types=1);

namespace App\Exceptions\Tenancy;

use RuntimeException;

class TenantContextMissingException extends RuntimeException
{
    public static function forModel(string $modelClass): self
    {
        return new self(
            sprintf('Tenant context is missing when accessing tenant-scoped model [%s].', $modelClass)
        );
    }
}
