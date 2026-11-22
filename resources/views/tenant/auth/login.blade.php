<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — {{ __('tenant_ui.login_title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; margin: 0; display: grid; place-items: center; min-height: 100vh; }
        .card { background: #fff; padding: 2rem; border-radius: .5rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); width: 100%; max-width: 380px; }
        label { display: block; margin-bottom: .25rem; font-size: .875rem; font-weight: 500; }
        input[type="email"], input[type="password"] { width: 100%; padding: .5rem .75rem; border: 1px solid #d1d5db; border-radius: .375rem; margin-bottom: 1rem; box-sizing: border-box; }
        button { width: 100%; padding: .625rem; background: #111827; color: #fff; border: none; border-radius: .375rem; cursor: pointer; font-weight: 600; }
        .error { color: #b91c1c; font-size: .875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $tenant->name }}</h1>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('tenant.login.store') }}">
            @csrf
            <label for="email">{{ __('tenant_ui.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">{{ __('tenant_ui.password') }}</label>
            <input id="password" type="password" name="password" required>

            <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;">
                <input type="checkbox" name="remember" value="1"> {{ __('tenant_ui.remember_me') }}
            </label>

            <button type="submit">{{ __('tenant_ui.log_in') }}</button>
        </form>
    </div>
</body>
</html>
