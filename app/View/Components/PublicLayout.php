<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public ?string $pageTitle = null,
    ) {}

    public function render(): View
    {
        return view('layouts.public', [
            'publicPageTitle' => $this->pageTitle,
        ]);
    }
}
