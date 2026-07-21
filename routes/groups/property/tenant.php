<?php

use App\Support\Property\PropertyWorkspaceTabs;
use App\Http\Controllers\Property\Agent\AgentPublicListingController;
use App\Http\Controllers\Property\Agent\AgentWorkspaceFormController;
use App\Http\Controllers\Property\Agent\DashboardController;
use App\Http\Controllers\Property\Agent\FinancialsController;
use App\Http\Controllers\Property\Agent\PerformanceWorkspaceController;
use App\Http\Controllers\Property\Agent\PmForwarderTokenController;
use App\Http\Controllers\Property\Agent\PmInvoiceController;
use App\Http\Controllers\Property\Agent\PmLeaseWebController;
use App\Http\Controllers\Property\Agent\PmMaintenanceWebController;
use App\Http\Controllers\Property\Agent\PmPaymentController;
use App\Http\Controllers\Property\Agent\PmTenantCreditController;
use App\Http\Controllers\Property\Agent\PmTenantDirectoryController;
use App\Http\Controllers\Property\Agent\PmVendorWebController;
use App\Http\Controllers\Property\Agent\PropertyAdvisorWebController;
use App\Http\Controllers\Property\Agent\PropertyAccountingController;
use App\Http\Controllers\Property\Agent\PropertyAmenityController;
use App\Http\Controllers\Property\Agent\PropertyCommunicationsWebController;
use App\Http\Controllers\Property\Agent\PropertyDataExportController;
use App\Http\Controllers\Property\Agent\PropertyListingsPipelineController;
use App\Http\Controllers\Property\Agent\PropertyPortfolioController;
use App\Http\Controllers\Property\Agent\PropertySettingsStoreWebController;
use App\Http\Controllers\Property\Agent\PropertyTeamUserController;
use App\Http\Controllers\Property\Agent\PropertySettingsWebController;
use App\Http\Controllers\Property\Agent\PropertyTenantsOpsWebController;
use App\Http\Controllers\Property\Agent\PropertyUtilityChargeController;
use App\Http\Controllers\Property\Agent\UtilityLedgerController;
use App\Http\Controllers\Property\Agent\UtilityAnalyticsController;
use App\Http\Controllers\Property\Agent\UtilityPeriodController;
use App\Http\Controllers\Property\Agent\PropertyReportsController;
use App\Http\Controllers\Property\Agent\RevenueController;
use App\Http\Controllers\Property\Agent\EquitySyncController;
use App\Http\Controllers\Property\Agent\FinanceDiagnosticsController;
use App\Http\Controllers\Property\Agent\AccountingReconciliationController;
use App\Http\Controllers\Property\Agent\FinanceIntegrityDashboardController;
use App\Http\Controllers\Property\Agent\FinancialReconciliationController;
use App\Http\Controllers\Property\Agent\PropertySearchController;
use App\Http\Controllers\Property\Landlord\LandlordPortalController;
use App\Http\Controllers\Property\PropertyPortalQuickActionController;
use App\Http\Controllers\Property\PropertyGeoController;
use App\Http\Controllers\Property\Tenant\TenantPortalController;
use App\Http\Controllers\Property\Tenant\TenantWorkspaceFormController;
use Illuminate\Support\Facades\Route;

Route::middleware(['property.portal:tenant'])->prefix('property/tenant')->name('property.tenant.')->group(function () {
    Route::get('/home', [TenantPortalController::class, 'home'])->name('home');
    Route::get('/credit', [TenantPortalController::class, 'creditHistory'])->name('credit.history');
    Route::get('/payments/pay', [TenantPortalController::class, 'pay'])->name('payments.pay');
    Route::post('/payments/pay', [TenantPortalController::class, 'paymentStore'])->name('payments.store');
    Route::post('/payments/stk', [TenantPortalController::class, 'stkIntentStore'])->name('payments.stk.store');
    Route::get('/payments/pending/{payment}', [TenantPortalController::class, 'pendingPayment'])->name('payments.pending');
    Route::get('/payments/pending/{payment}/status', [TenantPortalController::class, 'pendingPaymentStatus'])->name('payments.pending.status');
    Route::post('/payments/pending/{payment}/verify', [TenantPortalController::class, 'pendingPaymentVerify'])->name('payments.pending.verify');
    Route::get('/payments/history', [TenantPortalController::class, 'paymentsHistory'])->name('payments.history');
    Route::get('/payments/history/export', [TenantPortalController::class, 'paymentsHistoryExport'])->name('payments.history.export');
    Route::get('/payments/receipts', [TenantPortalController::class, 'receipts'])->name('payments.receipts');
    Route::get('/payments/receipts/{payment}', [TenantPortalController::class, 'showReceipt'])->name('payments.receipts.show');
    Route::get('/payments/receipts/{payment}/download', [TenantPortalController::class, 'downloadReceipt'])->name('payments.receipts.download');
    Route::get('/payments', [TenantPortalController::class, 'paymentsIndex'])->name('payments.index');

    Route::get('/invoices', [TenantPortalController::class, 'invoices'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [TenantPortalController::class, 'invoiceShow'])
        ->whereNumber('invoice')
        ->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [TenantPortalController::class, 'invoicePdf'])
        ->whereNumber('invoice')
        ->name('invoices.pdf');

    Route::get('/workspace/forms/{form}', [TenantWorkspaceFormController::class, 'show'])
        ->where('form', '[a-z0-9\-]+')
        ->name('workspace.form.show');
    Route::post('/workspace/forms/{form}', [TenantWorkspaceFormController::class, 'store'])
        ->where('form', '[a-z0-9\-]+')
        ->name('workspace.form.store');
    Route::get('/lease', [TenantPortalController::class, 'lease'])->name('lease');
    Route::get('/maintenance', [TenantPortalController::class, 'maintenanceIndex'])->name('maintenance.index');
    Route::get('/maintenance/report', [TenantPortalController::class, 'maintenanceReport'])->name('maintenance.report');
    Route::post('/maintenance/report', [TenantPortalController::class, 'maintenanceReportSubmit'])->name('maintenance.report.store');
    Route::get('/requests', [TenantPortalController::class, 'requestsPage'])->name('requests');
    Route::post('/requests', [TenantPortalController::class, 'storePortalRequest'])->name('requests.store');
    Route::get('/notifications', [TenantPortalController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [TenantPortalController::class, 'notificationsReadAll'])->name('notifications.read_all');
    Route::post('/notifications/{log}/read', [TenantPortalController::class, 'notificationsReadOne'])->whereNumber('log')->name('notifications.read_one');
    Route::get('/loans', [TenantPortalController::class, 'loans'])->name('loans');
    Route::post('/loans/apply', [TenantPortalController::class, 'applyLoan'])->name('loans.apply');
    Route::post('/loans/repay', [TenantPortalController::class, 'repayLoan'])->name('loans.repay');
    Route::get('/explore', [TenantPortalController::class, 'explore'])->name('explore');

    Route::get('/quick-action', function () {
        return redirect()
            ->route('property.tenant.home')
            ->with('success', 'That address is for form submissions only. Use an action button on the page you came from.');
    });
    Route::post('/quick-action', [PropertyPortalQuickActionController::class, 'storeTenant'])->name('quick_action.store');
});
