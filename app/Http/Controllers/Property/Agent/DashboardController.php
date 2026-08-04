<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\PropertyDashboardOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function metricsFrame(Request $request): View|Response
    {
        // Release the session lock before aggregates so other tabs/routes stay clickable.
        $request->session()->save();

        try {
            $data = PropertyDashboardOverview::metricsForAgent();
            $viewName = 'property.agent.partials.dashboard_metrics_light';

            if ($request->header('X-Property-Dashboard-Metrics') === '1') {
                return response()->view($viewName, $data);
            }

            return property_view('property.agent.partials.dashboard_metrics_frame', $data);
        } catch (\Throwable $e) {
            report($e);

            $message = config('app.debug')
                ? $e->getMessage()
                : __('Something went wrong while loading collections, charts, and activity.');

            if ($request->header('X-Property-Dashboard-Metrics') === '1') {
                return response()->view('property.agent.partials.dashboard_metrics_error', [
                    'message' => $message,
                ], 500);
            }

            throw $e;
        }
    }
}
