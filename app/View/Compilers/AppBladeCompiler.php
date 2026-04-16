<?php

namespace App\View\Compilers;

use Illuminate\View\Compilers\BladeCompiler;

/**
 * Resets @forelse stack state for each compile so the singleton BladeCompiler
 * cannot emit invalid placeholders like $__empty_-1 across views.
 */
class AppBladeCompiler extends BladeCompiler
{
    public function compileString($value)
    {
        $this->forElseCounter = 0;

        return parent::compileString($value);
    }
}
