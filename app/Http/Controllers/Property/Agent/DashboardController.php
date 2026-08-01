<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\PropertyDashboardOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** @deprecated Heavy metrics are always lazy-loaded; kept for backwards compatibility. */
    public const DEFER_METRICS_SESSION_KEY = 'defer_property_dashboard_metrics';

    public function commandCenter(Request $request): View
    {
        $request->session()->forget(self::DEFER_METRICS_SESSION_KEY);

        return property_view('property.agent.dashboard', PropertyDashboardOverview::lightForAgent());
    }

    public function metricsFrame(): View
    {
        return property_view('property.agent.partials.dashboard_metrics_frame', PropertyDashboardOverview::heavyForAgent());
    }
}
