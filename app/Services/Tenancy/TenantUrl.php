<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenancy\Domain;
use App\Models\Tenancy\Tenant;
use RuntimeException;

class TenantUrl
{
    public static function for(Tenant $tenant, string $path = '/'): string
    {
        /** @var Domain|null $domain */
        $domain = $tenant->relationLoaded('primaryDomain')
            ? $tenant->getRelation('primaryDomain')
            : $tenant->primaryDomain()->first();

        if ($domain === null) {
            $domain = $tenant->domains()->orderByDesc('is_primary')->orderBy('id')->first();
        }

        if ($domain === null) {
            throw new RuntimeException("Tenant [{$tenant->id}] has no domain configured.");
        }

        $appUrl = (string) config('app.url', 'http://localhost');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($appUrl, PHP_URL_PORT);
        $portSuffix = $port !== null ? ':'.$port : '';

        $normalizedPath = '/'.ltrim($path, '/');
        if ($path === '' || $path === '/') {
            $normalizedPath = '';
        }

        return sprintf('%s://%s%s%s', $scheme, $domain->fullHost(), $portSuffix, $normalizedPath);
    }
}
