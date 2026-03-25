<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChooseModuleController extends Controller
{
    public function show(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        $isSuperAdmin = (($user->is_super_admin ?? false) === true);
        $approvedModules = $isSuperAdmin
            ? ['property', 'loan']
            : ($user?->approvedModules() ?? []);

        if (count($approvedModules) === 0) {
            return redirect()->route('login')->withErrors([
                'module' => 'Your account is not approved for Property or Loan module access yet.',
            ]);
        }

        if (count($approvedModules) === 1) {
            $request->session()->put('active_system', $approvedModules[0]);
            $request->session()->regenerate();

            return redirect()->route('dashboard');
        }

        // If more than 1 module is approved, show chooser.
        $propertyApproved = $isSuperAdmin ? true : $user->isModuleApproved('property');
        $loanApproved = $isSuperAdmin ? true : $user->isModuleApproved('loan');

        return view('auth.choose_module', [
            'propertyApproved' => $propertyApproved,
            'loanApproved' => $loanApproved,
        ]);
    }

    public function activate(Request $request, string $module): RedirectResponse
    {
        abort_unless(in_array($module, ['property', 'loan'], true), 404);

        $user = $request->user();
        $isSuperAdmin = (($user->is_super_admin ?? false) === true);
        if (! $user || (! $isSuperAdmin && ! $user->isModuleApproved($module))) {
            $request->session()->forget(['active_system', 'url.intended']);

            return redirect()->route('login')->withErrors([
                'module' => "Your account is not approved for {$module} module access.",
            ]);
        }

        $request->session()->put('active_system', $module);
        $request->session()->forget('url.intended');
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}

