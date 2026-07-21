<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $heroTitle = null,
        public ?string $heroSubtitle = null,
        public ?string $heroCardLabel = null,
        public ?string $heroCardTitle = null,
        public ?string $heroCardBody = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest', [
            'title' => $this->title ?? config('app.name'),
            'heroTitle' => $this->heroTitle,
            'heroSubtitle' => $this->heroSubtitle,
            'heroCardLabel' => $this->heroCardLabel,
            'heroCardTitle' => $this->heroCardTitle,
            'heroCardBody' => $this->heroCardBody,
        ]);
    }
}
