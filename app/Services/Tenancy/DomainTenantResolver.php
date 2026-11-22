<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\Tenancy\DomainType;
use App\Models\Tenancy\Domain;
use App\Models\Tenancy\Tenant;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

class DomainTenantResolver
{
    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return rtrim($host, '.');
    }

    public function isCentralHost(string $host): bool
    {
        $normalized = $this->normalizeHost($host);
        $centralDomains = array_map(
            fn (string $domain): string => $this->normalizeHost($domain),
            (array) config('tenancy.central_domains', [])
        );

        return in_array($normalized, $centralDomains, true);
    }

    public function resolveByHost(string $host): ?Tenant
    {
        $normalized = $this->normalizeHost($host);

        if ($normalized === '' || $this->isCentralHost($normalized)) {
            return null;
        }

        $baseDomain = $this->normalizeHost((string) config('app.base_domain', 'localhost'));
        $suffix = '.'.$baseDomain;

        if ($baseDomain !== '' && str_ends_with($normalized, $suffix)) {
            $slug = substr($normalized, 0, -strlen($suffix));

            if ($slug === '' || str_contains($slug, '.')) {
                return null;
            }

            $domain = Domain::query()
                ->with('tenant')
                ->where('type', DomainType::Subdomain)
                ->where('domain', $slug)
                ->first();

            return $domain?->tenant;
        }

        $domain = Domain::query()
            ->with('tenant')
            ->where('type', DomainType::Custom)
            ->where('domain', $normalized)
            ->whereNotNull('verified_at')
            ->first();

        return $domain?->tenant;
    }

    /**
     * @throws TenantCouldNotBeIdentifiedOnDomainException
     */
    public function resolve(string $host): TenantContract
    {
        $tenant = $this->resolveByHost($host);

        if ($tenant === null) {
            throw new TenantCouldNotBeIdentifiedOnDomainException($host);
        }

        return $tenant;
    }
}
