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
        $deferHeavy = $request->header('Turbo-Frame') === 'property-main'
            || $request->session()->pull(self::DEFER_METRICS_SESSION_KEY, false);

        $data = PropertyDashboardOverview::lightForAgent();

        if (! $deferHeavy) {
            $data = array_merge($data, PropertyDashboardOverview::heavyForAgent());
        }

        return property_view('property.agent.dashboard', array_merge(
            $data,
            ['deferHeavyDashboardMetrics' => $deferHeavy],
        ));
    }

    public function metricsFrame(): View
    {
        return property_view('property.agent.partials.dashboard_metrics_frame', PropertyDashboardOverview::heavyForAgent());
    }
}
