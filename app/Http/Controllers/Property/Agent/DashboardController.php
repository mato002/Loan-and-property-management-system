<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\PropertyDashboardOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const DEFER_METRICS_SESSION_KEY = 'defer_property_dashboard_metrics';

    public function commandCenter(Request $request): View
    {
        $deferMetrics = $request->session()->pull(self::DEFER_METRICS_SESSION_KEY, false)
            || $request->header('Turbo-Frame') === 'property-main';

        if ($deferMetrics) {
            return property_view('property.agent.dashboard', [
                'deferDashboardMetrics' => true,
            ]);
        }

        return property_view('property.agent.dashboard', array_merge(
            PropertyDashboardOverview::forAgent(),
            ['deferDashboardMetrics' => false],
        ));
    }

    public function metricsFrame(): View
    {
        return property_view('property.agent.partials.dashboard_metrics_frame', PropertyDashboardOverview::forAgent());
    }
}
