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

    public function metricsFrame(Request $request): View
    {
        // Release the session lock before slow aggregates so other tabs/routes stay clickable.
        $request->session()->save();

        $data = PropertyDashboardOverview::heavyForAgent();

        if ($request->header('X-Property-Dashboard-Metrics') === '1') {
            return property_view('property.agent.partials.dashboard_stats_heavy', $data);
        }

        return property_view('property.agent.partials.dashboard_metrics_frame', $data);
    }
}
