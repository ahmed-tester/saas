<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Billing\FeatureGate;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(FeatureGate $featureGate): View
    {
        return view('tenant.dashboard', [
            'tenant' => tenant(),
            'user' => Auth::guard('web')->user(),
            'subscriptionState' => $featureGate->state(),
        ]);
    }
}
