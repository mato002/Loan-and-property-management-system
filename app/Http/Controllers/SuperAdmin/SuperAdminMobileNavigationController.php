<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\Property\PropertyMobileNavigationSettings;
use App\Support\Property\PropertyNavigation;
use App\Support\Property\PropertyWorkspaceTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminMobileNavigationController extends Controller
{
    public function index(): View
    {
        $workspaces = PropertyNavigation::agentWorkspaceDefinitions();
        $tabsByWorkspace = [];
        foreach ($workspaces as $workspace) {
            $key = (string) ($workspace['key'] ?? '');
            if ($key !== '') {
                $tabsByWorkspace[$key] = PropertyWorkspaceTabs::configurationTabsFor($key);
            }
        }
        $settings = PropertyMobileNavigationSettings::settings();

        return view('superadmin.console.mobile_navigation', compact('workspaces', 'tabsByWorkspace', 'settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hidden_workspaces' => ['sometimes', 'array'],
            'hidden_workspaces.*' => ['string'],
            'hidden_tabs' => ['sometimes', 'array'],
            'hidden_tabs.*' => ['array'],
            'hidden_tabs.*.*' => ['string'],
        ]);

        PropertyMobileNavigationSettings::save(
            $data['hidden_workspaces'] ?? [],
            $data['hidden_tabs'] ?? [],
        );

        return redirect()->route('superadmin.mobile_navigation')
            ->with('success', 'Mobile navigation settings saved.');
    }
}
