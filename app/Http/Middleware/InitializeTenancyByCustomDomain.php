<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\DomainTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByCustomDomain
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly DomainTenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if ($this->resolver->isCentralHost($host)) {
            abort(404);
        }

        $tenant = $this->resolver->resolveByHost($host);

        if ($tenant === null) {
            abort(404);
        }

        $this->tenancy->initialize($tenant);
        setPermissionsTeamId($tenant->getTenantKey());

        return $next($request);
    }
}
