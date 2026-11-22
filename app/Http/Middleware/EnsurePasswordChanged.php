<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (
            $user instanceof User
            && $user->must_change_password
            && ! $request->routeIs('tenant.password.change', 'tenant.password.update', 'tenant.logout')
        ) {
            if ($request->expectsJson()) {
                abort(403, 'Password change required.');
            }

            return redirect()->route('tenant.password.change');
        }

        return $next($request);
    }
}
