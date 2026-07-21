<?php

use App\Http\Controllers\SuperAdmin\SuperAdminAgentWorkspaceController;
use App\Http\Controllers\SuperAdmin\SuperAdminConsoleController;
use App\Http\Controllers\SuperAdmin\SuperAdminOpsConsoleController;
use App\Http\Controllers\SuperAdmin\SuperAdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', [SuperAdminConsoleController::class, 'dashboard'])->name('dashboard');
    Route::get('/access-approvals', [SuperAdminConsoleController::class, 'accessApprovals'])->name('access_approvals');
    Route::patch('/access-approvals/{access}', [SuperAdminConsoleController::class, 'updateAccessApproval'])->name('access_approvals.update');
    Route::post('/access-approvals/bulk', [SuperAdminConsoleController::class, 'bulkAccessApprovals'])->name('access_approvals.bulk');
    Route::get('/roles-permissions', [SuperAdminConsoleController::class, 'rolesPermissions'])->name('roles_permissions');
    Route::get('/agent-workspaces', [SuperAdminAgentWorkspaceController::class, 'index'])->name('agent_workspaces');
    Route::post('/agent-workspaces/bulk', [SuperAdminAgentWorkspaceController::class, 'bulk'])->name('agent_workspaces.bulk');
    Route::get('/agent-workspaces/{agent}', [SuperAdminAgentWorkspaceController::class, 'show'])->name('agent_workspaces.show');
    Route::post('/agent-workspaces/{agent}/impersonate', [SuperAdminAgentWorkspaceController::class, 'impersonate'])->name('agent_workspaces.impersonate');
    Route::post('/agent-workspaces/{agent}/transfer', [SuperAdminAgentWorkspaceController::class, 'transfer'])->name('agent_workspaces.transfer');
    Route::post('/agent-workspaces/{agent}/toggle-status', [SuperAdminAgentWorkspaceController::class, 'toggleStatus'])->name('agent_workspaces.toggle_status');
    Route::post('/agent-workspaces/{agent}/subscription', [SuperAdminAgentWorkspaceController::class, 'updateSubscription'])->name('agent_workspaces.subscription');
    Route::get('/audit-trail', [SuperAdminConsoleController::class, 'auditTrail'])->name('audit_trail');
    Route::post('/audit-trail/export-selected', [SuperAdminConsoleController::class, 'auditTrailExportSelected'])->name('audit_trail.export_selected');
    Route::get('/packages', [SuperAdminConsoleController::class, 'subscriptionPackages'])->name('console.packages');
    Route::post('/packages', [SuperAdminConsoleController::class, 'storeSubscriptionPackage'])->name('console.packages.store');
    Route::patch('/packages/{package}', [SuperAdminConsoleController::class, 'updateSubscriptionPackage'])->name('console.packages.update');
    Route::delete('/packages/{package}', [SuperAdminConsoleController::class, 'deleteSubscriptionPackage'])->name('console.packages.delete');
    Route::post('/packages/bulk', [SuperAdminConsoleController::class, 'bulkSubscriptionPackages'])->name('console.packages.bulk');

    Route::get('/subscriptions', [SuperAdminConsoleController::class, 'agentSubscriptions'])->name('console.subscriptions');
    Route::post('/subscriptions', [SuperAdminConsoleController::class, 'storeAgentSubscription'])->name('console.subscriptions.store');
    Route::post('/subscriptions/bulk', [SuperAdminConsoleController::class, 'bulkAgentSubscriptions'])->name('console.subscriptions.bulk');
    Route::delete('/subscriptions/{subscription}', [SuperAdminConsoleController::class, 'deleteAgentSubscription'])->name('console.subscriptions.delete');

    Route::get('/users', [SuperAdminUserController::class, 'index'])->name('users.index');
    Route::post('/users/bulk', [SuperAdminUserController::class, 'bulk'])->name('users.bulk');
    Route::get('/users/create', [SuperAdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [SuperAdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [SuperAdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [SuperAdminUserController::class, 'update'])->name('users.update');

    Route::get('/ops', [SuperAdminOpsConsoleController::class, 'index'])->name('ops.index');
    Route::post('/ops/landlord-scope/assign', [SuperAdminOpsConsoleController::class, 'assignLandlord'])->name('ops.landlord_scope.assign');
    Route::post('/ops/landlord-scope/release', [SuperAdminOpsConsoleController::class, 'releaseLandlord'])->name('ops.landlord_scope.release');
});
