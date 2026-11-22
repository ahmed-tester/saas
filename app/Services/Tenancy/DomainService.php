<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\Tenancy\DomainType;
use App\Models\Tenancy\Domain;
use App\Models\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DomainService
{
    public function addSubdomain(Tenant $tenant, string $slug, bool $isPrimary = false): Domain
    {
        $normalized = strtolower(trim($slug));

        return DB::transaction(function () use ($tenant, $normalized, $isPrimary): Domain {
            $hasAnyDomain = $tenant->domains()->exists();

            /** @var Domain $domain */
            $domain = $tenant->domains()->create([
                'domain' => $normalized,
                'type' => DomainType::Subdomain,
                'is_primary' => $isPrimary || ! $hasAnyDomain,
                'verified_at' => now(),
            ]);

            if ($isPrimary && $hasAnyDomain) {
                return $this->setPrimary($domain);
            }

            return $domain;
        });
    }

    public function addCustomDomain(Tenant $tenant, string $host): Domain
    {
        $normalized = $this->normalizeCustomHost($host);

        /** @var Domain $domain */
        $domain = $tenant->domains()->create([
            'domain' => $normalized,
            'type' => DomainType::Custom,
            'is_primary' => false,
            'verified_at' => null,
        ]);

        return $domain;
    }

    public function markVerified(Domain $domain): Domain
    {
        if ($domain->verified_at === null) {
            $domain->update(['verified_at' => now()]);
        }

        return $domain->refresh();
    }

    public function setPrimary(Domain $domain): Domain
    {
        if ($domain->type === DomainType::Custom && $domain->verified_at === null) {
            throw new InvalidArgumentException(__('tenancy.cannot_set_unverified_primary'));
        }

        return DB::transaction(function () use ($domain): Domain {
            Domain::query()
                ->where('tenant_id', $domain->tenant_id)
                ->where('id', '!=', $domain->id)
                ->update(['is_primary' => false]);

            $domain->update(['is_primary' => true]);

            return $domain->refresh();
        });
    }

    public function delete(Domain $domain): void
    {
        if ($domain->is_primary) {
            throw new InvalidArgumentException(__('tenancy.cannot_delete_primary_domain'));
        }

        $domain->delete();
    }

    private function normalizeCustomHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        return rtrim($host, '.');
    }
}
