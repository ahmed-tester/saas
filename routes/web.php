<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

foreach ((array) config('tenancy.central_domains', []) as $centralDomain) {
    Route::domain($centralDomain)->group(function (): void {
        Route::get('/', function (Request $request) {
            $adminDomain = (string) config('app.admin_domain', 'localhost');
            $loginPath = route('filament.admin.auth.login', [], false);
            $port = $request->getPort();
            $isDefaultPort = ($request->isSecure() && $port === 443) || (! $request->isSecure() && $port === 80);
            $portSuffix = $isDefaultPort ? '' : ':'.$port;

            return redirect()->away("{$request->getScheme()}://{$adminDomain}{$portSuffix}{$loginPath}");
        });
    });
}
