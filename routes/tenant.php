<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Auth\LoginController;
use App\Http\Controllers\Tenant\Auth\PasswordChangeController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\InitializeTenancyByCustomDomain;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    InitializeTenancyByCustomDomain::class,
    EnsureTenantIsActive::class,
    EnsureUserBelongsToTenant::class,
])->group(function (): void {
    Route::middleware('guest:web')->group(function (): void {
        Route::get('/login', [LoginController::class, 'show'])->name('tenant.login');
        Route::post('/login', [LoginController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('tenant.login.store');
    });

    Route::middleware(['auth:web', EnsurePasswordChanged::class])->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('tenant.logout');
        Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('tenant.password.change');
        Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('tenant.password.update');
        Route::get('/', DashboardController::class)->name('tenant.dashboard');
    });
});
