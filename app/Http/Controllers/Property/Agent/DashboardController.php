<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\PropertyDashboardOverview;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function commandCenter(): View
    {
        return property_view('property.agent.dashboard', PropertyDashboardOverview::forAgent());
    }
}
