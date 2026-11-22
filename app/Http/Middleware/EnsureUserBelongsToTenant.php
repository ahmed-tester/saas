<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $currentTenantId = tenancy()->initialized ? (int) tenant()->getTenantKey() : null;

        if ($currentTenantId === null || (int) $user->tenant_id !== $currentTenantId || ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, __('tenancy.user_inactive'));
            }

            return redirect()->route('tenant.login');
        }

        return $next($request);
    }
}
