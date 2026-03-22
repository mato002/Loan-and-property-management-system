<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PropertyLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $role = auth()->user()?->property_portal_role ?? 'agent';
        if (! in_array($role, ['agent', 'landlord', 'tenant'], true)) {
            $role = 'agent';
        }

        return view('layouts.property', ['propertyPortal' => $role]);
    }
}
