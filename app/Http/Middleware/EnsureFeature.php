<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Billing\FeatureKey;
use App\Services\Billing\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeature
{
    public function __construct(
        private readonly FeatureGate $gate,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $key = FeatureKey::from($feature);
        $isRead = in_array($request->method(), ['GET', 'HEAD'], true);

        $allowed = $isRead
            ? $this->gate->canRead($key)
            : $this->gate->canWrite($key);

        if ($allowed) {
            return $next($request);
        }

        $isReadOnly = ! $isRead && $this->gate->canRead($key);
        $code = $isReadOnly ? 'feature_read_only' : 'feature_locked';
        $message = $isReadOnly ? __('billing.feature_read_only') : __('billing.feature_locked');

        if ($request->expectsJson()) {
            return response()->json([
                'code' => $code,
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}
