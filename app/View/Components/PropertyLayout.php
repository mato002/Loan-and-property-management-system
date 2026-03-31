<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PropertyLayout extends Component
{
    private function inferRole(): string
    {
        $user = auth()->user();
        $role = trim((string) ($user?->property_portal_role ?? ''));
        if (in_array($role, ['agent', 'landlord', 'tenant'], true)) {
            return $role;
        }

        // Fallback for older DBs / missing column values: infer from linked portal profiles.
        try {
            if ($user && method_exists($user, 'pmTenantProfile') && $user->pmTenantProfile()->exists()) {
                return 'tenant';
            }
            if ($user && method_exists($user, 'landlordProperties') && $user->landlordProperties()->exists()) {
                return 'landlord';
            }
        } catch (\Throwable) {
            // ignore inference errors, default below
        }

        return 'agent';
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $role = $this->inferRole();

        return view('layouts.property', ['propertyPortal' => $role]);
    }
}
