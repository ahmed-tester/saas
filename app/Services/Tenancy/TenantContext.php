<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;

class TenantContext
{
    private static int $centralDepth = 0;

    /**
     * Execute a callback within an explicit central (non-tenant) context.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function central(Closure $callback): mixed
    {
        self::$centralDepth++;

        try {
            return $callback();
        } finally {
            self::$centralDepth--;
        }
    }

    public static function isCentral(): bool
    {
        return self::$centralDepth > 0;
    }
}
