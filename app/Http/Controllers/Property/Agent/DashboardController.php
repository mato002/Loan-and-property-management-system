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

    public function metricsFrame(Request $request): View|\Illuminate\Http\Response
    {
        // Release the session lock before slow aggregates so other tabs/routes stay clickable.
        $request->session()->save();

        try {
            $data = PropertyDashboardOverview::heavyForAgent();
        } catch (\Throwable $e) {
            report($e);

            if ($request->header('X-Property-Dashboard-Metrics') === '1') {
                return response()->view('property.agent.partials.dashboard_metrics_error', [
                    'message' => config('app.debug')
                        ? $e->getMessage()
                        : __('Something went wrong while loading collections, charts, and activity.'),
                ], 500);
            }

            throw $e;
        }

        if ($request->header('X-Property-Dashboard-Metrics') === '1') {
            return property_view('property.agent.partials.dashboard_stats_heavy', $data);
        }

        return property_view('property.agent.partials.dashboard_metrics_frame', $data);
    }
}
