<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if ($tenant === null) {
            abort(404);
        }

        if ($tenant->status === TenantStatus::Suspended) {
            abort(403, __('tenancy.suspended'));
        }

        return $next($request);
    }
}
