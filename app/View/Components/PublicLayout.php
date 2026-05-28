<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public ?string $pageTitle = null,
        public ?string $pageDescription = null,
        public ?string $pageImage = null,
        public ?string $pageRobots = null,
    ) {}

    public function render(): View
    {
        $data = array_filter([
            'publicPageTitle' => $this->pageTitle,
            'publicPageDescription' => $this->pageDescription,
            'publicPageImage' => $this->pageImage,
            'publicPageRobots' => $this->pageRobots,
        ], fn ($value) => $value !== null && trim((string) $value) !== '');

        return view('layouts.public', $data);
    }
}
