<?php

use App\Http\Controllers\Property\Agent\PropertyPortfolioController;
use Illuminate\Support\Facades\Route;

// Property portal is used by tenants/landlords who may not have verified emails.
// Keep auth + module access + active system checks, but do not block by `verified`.
Route::middleware(['auth', 'module.access:property', 'property.system'])->group(function () {

    // Impersonation "stop" must be available even while impersonating (landlord/tenant).
    Route::post('/property/impersonation/stop', [PropertyPortfolioController::class, 'stopImpersonation'])
        ->name('property.impersonation.stop');

    require __DIR__.'/groups/property/agent.php';
    require __DIR__.'/groups/property/landlord.php';
    require __DIR__.'/groups/property/tenant.php';
});
