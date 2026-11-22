<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — {{ __('tenant_ui.dashboard_title') }}</title>
</head>
<body style="font-family: system-ui, sans-serif; padding: 2rem;">
    <h1>{{ $tenant->name }}</h1>
    <p>{{ __('tenant_ui.welcome') }}, {{ $user->name }} ({{ $user->email }})</p>
    <p>{{ __('tenant_ui.subscription') }}: {{ $subscriptionState->label() }}</p>

    <form method="POST" action="{{ route('tenant.logout') }}">
        @csrf
        <button type="submit">{{ __('tenant_ui.log_out') }}</button>
    </form>
</body>
</html>
