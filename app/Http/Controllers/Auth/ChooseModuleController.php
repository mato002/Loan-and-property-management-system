<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\Agent\DashboardController;
use App\Support\Auth\StaffModuleRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChooseModuleController extends Controller
{
    public function show(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        $approvedModules = StaffModuleRedirect::approvedModules($user);

        if ($approvedModules === []) {
            return redirect()->route('login')->withErrors([
                'module' => 'Your account is not approved for Property or Loan module access yet.',
            ]);
        }

        if (count($approvedModules) === 1) {
            return $this->enterModule($request, $user, $approvedModules[0]);
        }

        return view('auth.choose_module', [
            'propertyApproved' => in_array('property', $approvedModules, true),
            'loanApproved' => in_array('loan', $approvedModules, true),
            'title' => __('Choose module').' — '.config('app.name'),
            'heroCardTitle' => __('Choose a module'),
            'heroCardBody' => __('Pick Property or Loan to open your workspace.'),
        ]);
    }

    public function activate(Request $request, string $module): RedirectResponse
    {
        abort_unless(in_array($module, ['property', 'loan'], true), 404);

        $user = $request->user();
        if (! $user || ! StaffModuleRedirect::isModuleAllowed($user, $module)) {
            $request->session()->forget(['active_system', 'url.intended']);

            return redirect()->route('login')->withErrors([
                'module' => "Your account is not approved for {$module} module access.",
            ]);
        }

        return $this->enterModule($request, $user, $module);
    }

    private function enterModule(Request $request, $user, string $module): RedirectResponse
    {
        StaffModuleRedirect::rememberModule($request, $module);

        if ($module === 'property') {
            $request->session()->flash(DashboardController::DEFER_METRICS_SESSION_KEY, true);
        }

        $routeName = StaffModuleRedirect::destinationRouteName($user, $module);

        return redirect()
            ->route($routeName)
            ->withCookie(cookie(
                StaffModuleRedirect::PREFERRED_MODULE_COOKIE,
                $module,
                60 * 24 * 180,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'Lax',
            ));
    }
}
