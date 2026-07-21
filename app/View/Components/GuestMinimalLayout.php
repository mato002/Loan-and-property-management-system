<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestMinimalLayout extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {}

    public function render(): View
    {
        return view('layouts.guest_minimal', [
            'title' => $this->title ?? config('app.name'),
        ]);
    }
}
