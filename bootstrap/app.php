<?php

use App\Http\Middleware\ConfigureViteHotRequests;
use App\Http\Middleware\EnsureActivePropertySystem;
use App\Http\Middleware\EnsurePropertyPermission;
use App\Http\Middleware\EnsurePropertyPortalRole;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\LogLoanPortalAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/property_portal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', ConfigureViteHotRequests::class);
        $middleware->appendToGroup('web', LogLoanPortalAccess::class);
        $middleware->alias([
            'property.system' => EnsureActivePropertySystem::class,
            'property.portal' => EnsurePropertyPortalRole::class,
            'property.permission' => EnsurePropertyPermission::class,
            'module.access' => EnsureModuleAccess::class,
            'superadmin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
