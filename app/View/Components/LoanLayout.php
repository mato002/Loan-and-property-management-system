<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class LoanLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $view = request()->header('Turbo-Frame') === 'loan-main'
            ? 'layouts.loan_frame'
            : 'layouts.loan';

        return view($view);
    }
}
