<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('tenant.auth.login', [
            'tenant' => tenant(),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => strtolower(trim((string) $request->validated('email'))),
            'password' => (string) $request->validated('password'),
            'tenant_id' => (int) tenant()->getTenantKey(),
            'is_active' => true,
        ];

        $remember = (bool) $request->validated('remember', false);

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => [__('tenancy.wrong_tenant')],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('tenant.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }
}
