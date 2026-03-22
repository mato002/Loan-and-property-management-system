<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestPropertyPortalLayout extends Component
{
    public function __construct(
        public string $portal,
    ) {
        if (! in_array($this->portal, ['tenant', 'landlord'], true)) {
            $this->portal = 'tenant';
        }
    }

    public function render(): View
    {
        return view('layouts.guest_property_portal', [
            'portal' => $this->portal,
        ]);
    }
}
