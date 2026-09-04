<?php

use App\Support\Property\PropertyWorkspaceTabs;
use App\Http\Controllers\Property\Agent\AgentPublicListingController;
use App\Http\Controllers\Property\Agent\AgentWorkspaceFormController;
use App\Http\Controllers\Property\Agent\DashboardController;
use App\Http\Controllers\Property\Agent\FieldOfficerController;
use App\Http\Controllers\Property\Agent\PropertyHrEmployeesController;
use App\Http\Controllers\Property\Agent\PropertyHrLeavesController;
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
use App\Http\Controllers\Property\Agent\PropertyOffboardingController;
use App\Http\Controllers\Property\Agent\PropertyPortfolioController;
use App\Http\Controllers\Property\Agent\PropertySettingsStoreWebController;
use App\Http\Controllers\Property\Agent\PropertyTeamUserController;
use App\Http\Controllers\Property\Agent\PropertyActivityLogController;
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

Route::middleware(['property.portal:agent'])->prefix('property')->name('property.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'commandCenter'])->name('dashboard');
    Route::get('/dashboard/metrics', [DashboardController::class, 'metricsFrame'])->name('dashboard.metrics');
    Route::get('/search', [PropertySearchController::class, 'index'])->name('search');
    Route::get('/search/suggest', [PropertySearchController::class, 'suggest'])->name('search.suggest');

    Route::get('/workspace/forms/{form}', [AgentWorkspaceFormController::class, 'show'])
        ->where('form', '[a-z0-9\-]+')
        ->name('workspace.form.show');
    Route::post('/workspace/forms/{form}', [AgentWorkspaceFormController::class, 'store'])
        ->where('form', '[a-z0-9\-]+')
        ->name('workspace.form.store');

    Route::get('/revenue/rent-roll', [RevenueController::class, 'rentRoll'])->name('revenue.rent_roll');
    Route::get('/revenue/uninvoiced-leases', [RevenueController::class, 'uninvoicedLeases'])->name('revenue.uninvoiced_leases');
    Route::post('/revenue/uninvoiced-leases/generate', [RevenueController::class, 'generateUninvoicedInvoices'])
        ->middleware('property.permission:invoices.manage')
        ->name('revenue.uninvoiced_leases.generate');
    Route::post('/revenue/uninvoiced-leases/generate-supplements', [RevenueController::class, 'generateRentSupplements'])
        ->middleware('property.permission:invoices.manage')
        ->name('revenue.uninvoiced_leases.generate_supplements');
    Route::get('/revenue/arrears', [RevenueController::class, 'arrears'])->name('revenue.arrears');
    Route::get('/revenue/arrears/tenant/{tenant}', [RevenueController::class, 'arrearsTenant'])
        ->whereNumber('tenant')
        ->name('revenue.arrears.tenant');
    Route::post('/revenue/arrears/reminders', [RevenueController::class, 'sendArrearsReminders'])
        ->middleware('property.permission:communications.manage')
        ->name('revenue.arrears.reminders');
    Route::post('/revenue/invoices/bulk', [RevenueController::class, 'invoicesBulk'])->name('revenue.invoices.bulk');
    Route::post('/revenue/payments/bulk', [RevenueController::class, 'paymentsBulk'])->name('revenue.payments.bulk');
    Route::post('/revenue/arrears/reminders/test-email', [RevenueController::class, 'sendArrearsTestEmail'])
        ->middleware('property.permission:communications.manage')
        ->name('revenue.arrears.reminders.test_email');
    Route::get('/revenue/invoices', [PmInvoiceController::class, 'invoices'])->name('revenue.invoices');
    Route::post('/revenue/invoices', [PmInvoiceController::class, 'store'])->middleware('property.permission:invoices.manage')->name('invoices.store');
    Route::post('/revenue/invoices/types', [PmInvoiceController::class, 'storeCustomType'])->middleware('property.permission:invoices.manage')->name('invoices.types.store');
    Route::get('/revenue/invoices/{invoice}/edit', [PmInvoiceController::class, 'edit'])->name('revenue.invoices.edit');
    Route::put('/revenue/invoices/{invoice}', [PmInvoiceController::class, 'update'])->middleware('property.permission:invoices.manage')->name('revenue.invoices.update');
    Route::patch('/revenue/invoices/{invoice}/status', [PmInvoiceController::class, 'updateStatus'])->middleware('property.permission:invoices.manage')->name('revenue.invoices.status');
    Route::post('/revenue/invoices/{invoice}/mark-sent', [PmInvoiceController::class, 'markSent'])->middleware('property.permission:invoices.manage')->name('revenue.invoices.mark_sent');
    Route::post('/revenue/invoices/{invoice}/cancel', [PmInvoiceController::class, 'cancel'])->middleware('property.permission:invoices.manage')->name('revenue.invoices.cancel');
    Route::post('/revenue/invoices/{invoice}/reopen', [PmInvoiceController::class, 'reopen'])->middleware('property.permission:invoices.manage')->name('revenue.invoices.reopen');
    Route::post('/revenue/invoices/{invoice}/record-payment', [PmInvoiceController::class, 'recordPayment'])->middleware('property.permission:payments.record')->name('revenue.invoices.record_payment');
    Route::get('/revenue/invoices/{invoice}/pdf', [PmInvoiceController::class, 'downloadPdf'])->name('revenue.invoices.pdf');
    Route::get('/revenue/invoices/{invoice}/print', [PmInvoiceController::class, 'printable'])->name('revenue.invoices.print');
    Route::post('/revenue/invoices/{invoice}/send', [PmInvoiceController::class, 'sendToTenant'])->middleware('property.permission:communications.manage')->name('revenue.invoices.send');
    Route::post('/revenue/invoices/{invoice}/credit-note', [PmInvoiceController::class, 'createCreditNote'])->middleware('property.permission:invoices.manage')->name('revenue.invoices.credit_note');
    Route::get('/revenue/invoices/lease/{lease}/info', [PmInvoiceController::class, 'leaseInfo'])->name('invoices.lease_info');
    Route::get('/revenue/invoices/{invoice}', [PmInvoiceController::class, 'show'])->name('revenue.invoices.show');
    Route::delete('/revenue/invoices/{invoice}', [PmInvoiceController::class, 'destroy'])->middleware('property.permission:invoices.manage')->name('revenue.invoices.destroy');
    Route::get('/revenue/penalties', [RevenueController::class, 'penalties'])->name('revenue.penalties');
    Route::post('/revenue/penalties', [RevenueController::class, 'storePenaltyRule'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.penalties.store');
    Route::delete('/revenue/penalties/{penalty_rule}', [RevenueController::class, 'destroyPenaltyRule'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.penalties.destroy');
    Route::get('/revenue/payments', [PmPaymentController::class, 'payments'])->name('revenue.payments');
    Route::post('/revenue/payments', [PmPaymentController::class, 'store'])->middleware('property.permission:payments.record')->name('payments.store');
    Route::post('/revenue/payments/advance', [PmPaymentController::class, 'storeAdvance'])->middleware('property.permission:payments.record')->name('payments.store_advance');
    Route::patch('/revenue/payments/{payment}/settle', [PmPaymentController::class, 'settle'])->middleware('property.permission:payments.settle')->name('payments.settle');
    Route::post('/revenue/payments/{payment}/reversal/request', [PmPaymentController::class, 'requestReversal'])->middleware('property.permission:payments.settle')->name('payments.reversal.request');
    Route::post('/revenue/payments/{payment}/reversal/approve', [PmPaymentController::class, 'approveReversal'])->middleware('property.permission:payments.settle')->name('payments.reversal.approve');
    Route::get('/revenue/payments/{payment}/receipt', [PmPaymentController::class, 'showReceipt'])->name('payments.receipt.show');
    Route::get('/revenue/payments/{payment}/receipt/download', [PmPaymentController::class, 'downloadReceipt'])->name('payments.receipt.download');
    Route::get('/revenue/tenant-credits', [PmTenantCreditController::class, 'report'])->name('revenue.tenant_credits');
    Route::get('/tenants/{tenant}/credit', [PmTenantCreditController::class, 'ledger'])->name('tenants.credit.ledger');
    Route::post('/tenants/{tenant}/credit/apply', [PmTenantCreditController::class, 'apply'])->middleware('property.permission:payments.record')->name('tenants.credit.apply');
    Route::post('/tenants/{tenant}/credit/refund', [PmTenantCreditController::class, 'refund'])->middleware('property.permission:payments.record')->name('tenants.credit.refund');
    Route::post('/tenants/{tenant}/credit/auto-apply', [PmTenantCreditController::class, 'autoApply'])->middleware('property.permission:payments.record')->name('tenants.credit.auto_apply');
    Route::get('/revenue/receipts', [RevenueController::class, 'receipts'])->name('revenue.receipts');
    Route::get('/revenue/utilities-charges', [PropertyUtilityChargeController::class, 'index'])->name('revenue.utilities');
    Route::post('/revenue/utilities-charges', [PropertyUtilityChargeController::class, 'store'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.store');
    Route::get('/revenue/utilities-charges/water-readings/default-previous', [PropertyUtilityChargeController::class, 'waterDefaultPreviousReadings'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_readings.default_previous');
    Route::post('/revenue/utilities-charges/water-readings', [PropertyUtilityChargeController::class, 'storeWaterReading'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_readings.store');
    Route::patch('/revenue/utilities-charges/water-readings/{reading}', [PropertyUtilityChargeController::class, 'updateWaterReading'])
        ->middleware('property.permission:revenue.utilities.manage')
        ->name('revenue.utilities.water_readings.update');
    Route::post('/revenue/utilities-charges/water-readings/bulk', [PropertyUtilityChargeController::class, 'storeBulkWaterReadings'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_readings.bulk');
    Route::post('/revenue/utilities-charges/water-readings/bulk-action', [PropertyUtilityChargeController::class, 'waterReadingsBulkAction'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_readings.bulk_action');
    Route::post('/revenue/utilities-charges/invoices', [PropertyUtilityChargeController::class, 'generateUtilityInvoices'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.invoices.generate');
    Route::post('/revenue/utilities-charges/materialize-attached', [PropertyUtilityChargeController::class, 'materializeAttachedCharges'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.attached.materialize');
    Route::post('/revenue/utilities-charges/water-invoices', [PropertyUtilityChargeController::class, 'generateWaterInvoices'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_invoices.generate');
    Route::post('/revenue/utilities-charges/water-supplements', [PropertyUtilityChargeController::class, 'generateWaterSupplements'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_supplements.generate');
    Route::post('/revenue/utilities-charges/water-penalties/apply', [PropertyUtilityChargeController::class, 'applyWaterPenalties'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.utilities.water_penalties.apply');
    Route::get('/revenue/utilities-charges/water-penalties/preview', [PropertyUtilityChargeController::class, 'previewWaterPenalties'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.utilities.water_penalties.preview');
    Route::post('/revenue/utilities-charges/water-penalties/reverse', [PropertyUtilityChargeController::class, 'reverseWaterPenalty'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.utilities.water_penalties.reverse');
    Route::delete('/revenue/utilities-charges/{charge}', [PropertyUtilityChargeController::class, 'destroy'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.destroy');
    Route::get('/revenue/utilities/reconciliation', [UtilityLedgerController::class, 'reconciliation'])->name('revenue.utilities.reconciliation');
    Route::get('/revenue/utilities/ledger', [UtilityLedgerController::class, 'index'])->name('revenue.utilities.ledger');
    Route::get('/revenue/utilities/analytics', [UtilityAnalyticsController::class, 'index'])->name('revenue.utilities.analytics');
    Route::get('/revenue/utilities/periods', [UtilityPeriodController::class, 'index'])->name('revenue.utilities.periods');
    Route::get('/revenue/utilities/periods/{billingMonth}', [UtilityPeriodController::class, 'show'])->name('revenue.utilities.periods.show');
    Route::post('/revenue/utilities/periods/{billingMonth}/close', [UtilityPeriodController::class, 'close'])->middleware('property.permission:revenue.utilities.period_close')->name('revenue.utilities.periods.close');
    Route::get('/revenue/utilities/periods/{billingMonth}/close-report', [UtilityPeriodController::class, 'closeReport'])->name('revenue.utilities.periods.close_report');
    Route::post('/revenue/utilities/periods/{billingMonth}/overrides', [UtilityPeriodController::class, 'requestOverride'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.periods.overrides.request');
    Route::post('/revenue/utilities/period-overrides/{override}/approve', [UtilityPeriodController::class, 'approveOverride'])->middleware('property.permission:revenue.utilities.period_override_approve')->name('revenue.utilities.periods.overrides.approve');
    Route::post('/revenue/utilities/period-overrides/{override}/reject', [UtilityPeriodController::class, 'rejectOverride'])->middleware('property.permission:revenue.utilities.period_override_approve')->name('revenue.utilities.periods.overrides.reject');
    Route::get('/tenants/{tenant}/utility-statement', [UtilityLedgerController::class, 'tenantStatement'])->name('tenants.utility.statement');
    Route::get('/revenue/equity/sync-status', [EquitySyncController::class, 'syncStatus'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.sync_status');
    Route::post('/revenue/equity/sync-status/sync', [EquitySyncController::class, 'triggerSync'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.sync_status.sync');
    Route::get('/revenue/equity/unmatched', [EquitySyncController::class, 'unmatchedPayments'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.unmatched');
    Route::get('/revenue/equity/unmatched/export', [EquitySyncController::class, 'unmatchedPaymentsExport'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.unmatched.export');
    Route::get('/revenue/equity/unmatched/print', [EquitySyncController::class, 'unmatchedPaymentsPrint'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.unmatched.print');
    Route::get('/revenue/equity/unmatched/{unassignedPayment}', [EquitySyncController::class, 'showUnmatchedPayment'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.unmatched.show');
    Route::post('/revenue/equity/unmatched/rematch-all', [EquitySyncController::class, 'rematchAllUnmatchedPayments'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.unmatched.rematch_all');
    Route::post('/revenue/equity/unmatched/{unassignedPayment}/rematch', [EquitySyncController::class, 'rematchUnmatchedPayment'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.unmatched.rematch');
    Route::post('/revenue/equity/unmatched/{unassignedPayment}/assign', [EquitySyncController::class, 'assignUnmatchedPayment'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.unmatched.assign');
    Route::get('/revenue/equity/matched', [EquitySyncController::class, 'matchedPayments'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.matched');
    Route::get('/revenue/equity/all', [EquitySyncController::class, 'allPayments'])
        ->middleware('property.permission:payments.settle')
        ->name('equity.all');
    Route::get('/revenue/overview', [RevenueController::class, 'collectionsOverview'])->name('revenue.overview');
    Route::get('/revenue', [RevenueController::class, 'collectionsOverview'])->name('revenue.index');

    Route::get('/tenants/directory', [PmTenantDirectoryController::class, 'directory'])->name('tenants.directory');
    Route::get('/tenants/directory/export.csv', [PmTenantDirectoryController::class, 'exportDirectoryCsv'])
        ->middleware('property.permission:tenants.manage')
        ->name('tenants.directory.export');
    Route::get('/tenants/leases', [PmLeaseWebController::class, 'leases'])->name('tenants.leases');
    Route::get('/tenants/expiry', [PmLeaseWebController::class, 'expiry'])->name('tenants.expiry');
    Route::get('/tenants/profiles', [PmTenantDirectoryController::class, 'profiles'])->name('tenants.profiles');
    Route::get('/tenants/import', [PmTenantDirectoryController::class, 'importForm'])
        ->middleware('property.permission:tenants.manage')
        ->name('tenants.import');
    Route::get('/tenants/import/template.csv', [PmTenantDirectoryController::class, 'importTemplate'])
        ->middleware('property.permission:tenants.manage')
        ->name('tenants.import.template');
    Route::post('/tenants/import', [PmTenantDirectoryController::class, 'importStore'])
        ->middleware('property.permission:tenants.manage')
        ->name('tenants.import.store');
    Route::post('/tenants', [PmTenantDirectoryController::class, 'store'])->middleware('property.permission:tenants.manage')->name('tenants.store');
    Route::post('/tenants/create-json', [PmTenantDirectoryController::class, 'storeJson'])->middleware('property.permission:tenants.manage')->name('tenants.store_json');
    Route::get('/tenants/{tenant}', [PmTenantDirectoryController::class, 'show'])->whereNumber('tenant')->name('tenants.show');
    Route::get('/tenants/{tenant}/statement', [PmTenantDirectoryController::class, 'statement'])->whereNumber('tenant')->name('tenants.statement');
    Route::post('/tenants/{tenant}/repair-allocations', [PmTenantDirectoryController::class, 'repairAllocations'])
        ->whereNumber('tenant')
        ->middleware('property.permission:payments.settle')
        ->name('tenants.repair_allocations');
    Route::get('/tenants/{tenant}/edit', [PmTenantDirectoryController::class, 'edit'])->whereNumber('tenant')->name('tenants.edit');
    Route::put('/tenants/{tenant}', [PmTenantDirectoryController::class, 'update'])->whereNumber('tenant')->middleware('property.permission:tenants.manage')->name('tenants.update');
    Route::delete('/tenants/{tenant}', [PmTenantDirectoryController::class, 'destroy'])->whereNumber('tenant')->middleware('property.permission:tenants.manage')->name('tenants.destroy');
    Route::get('/leases/create-form', [PmLeaseWebController::class, 'createForm'])->middleware('property.permission:leases.manage')->name('leases.create_form');
    Route::get('/leases/form/tenants', [PmLeaseWebController::class, 'formTenants'])->middleware('property.permission:leases.manage')->name('leases.form_tenants');
    Route::get('/leases/form/vacant-units', [PmLeaseWebController::class, 'formVacantUnits'])->middleware('property.permission:leases.manage')->name('leases.form_vacant_units');
    Route::get('/leases/form/property-rules', [PmLeaseWebController::class, 'formPropertyRules'])->middleware('property.permission:leases.manage')->name('leases.form_property_rules');
    Route::post('/leases', [PmLeaseWebController::class, 'store'])->middleware('property.permission:leases.manage')->name('leases.store');
    Route::post('/leases/bulk', [PmLeaseWebController::class, 'bulk'])->middleware('property.permission:leases.manage')->name('leases.bulk');
    Route::post('/leases/bulk', [PmLeaseWebController::class, 'bulk'])->middleware('property.permission:leases.manage')->name('leases.bulk');
    Route::get('/leases/{lease}', [PmLeaseWebController::class, 'show'])->name('leases.show');
    Route::get('/leases/{lease}/edit', [PmLeaseWebController::class, 'edit'])->name('leases.edit');
    Route::put('/leases/{lease}', [PmLeaseWebController::class, 'update'])->middleware('property.permission:leases.manage')->name('leases.update');
    Route::post('/leases/{lease}/terminate', [PmLeaseWebController::class, 'terminate'])->middleware('property.permission:leases.manage')->name('leases.terminate');
    Route::post('/leases/{lease}/restore', [PmLeaseWebController::class, 'restore'])->middleware('property.permission:leases.manage')->name('leases.restore');
    Route::delete('/leases/{lease}', [PmLeaseWebController::class, 'destroy'])->middleware('property.permission:leases.manage')->name('leases.destroy');
    Route::get('/tenants/movements', [PropertyTenantsOpsWebController::class, 'movements'])->name('tenants.movements');
    Route::get('/tenants/movements/export', [PropertyTenantsOpsWebController::class, 'movementsExport'])->name('tenants.movements.export');
    Route::post('/tenants/movements', [PropertyTenantsOpsWebController::class, 'storeMovement'])->middleware('property.permission:tenants.manage')->name('tenants.movements.store');
    Route::post('/tenants/movements/{movement}/status', [PropertyTenantsOpsWebController::class, 'updateMovementStatus'])->middleware('property.permission:tenants.manage')->name('tenants.movements.status');
    Route::get('/tenants/notices', [PropertyTenantsOpsWebController::class, 'notices'])->name('tenants.notices');
    Route::get('/tenants/notices/export', [PropertyTenantsOpsWebController::class, 'noticesExport'])->name('tenants.notices.export');
    Route::post('/tenants/notices', [PropertyTenantsOpsWebController::class, 'storeNotice'])->middleware('property.permission:tenants.manage')->name('tenants.notices.store');
    Route::post('/tenants/notices/bulk', [PropertyTenantsOpsWebController::class, 'noticesBulk'])->middleware('property.permission:tenants.manage')->name('tenants.notices.bulk');
    Route::post('/tenants/notices/{notice}/status', [PropertyTenantsOpsWebController::class, 'updateNoticeStatus'])->middleware('property.permission:tenants.manage')->name('tenants.notices.status');
    Route::get('/tenants', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('tenants'))->name('tenants.index');
    Route::get('/reports', fn () => redirect()->route('property.reports.tenant'))->name('reports.center');
    Route::view('/reports/tenant', 'property.agent.reports.tenant.index')->name('reports.tenant');
    Route::view('/reports/landlord', 'property.agent.reports.landlord.index')->name('reports.landlord');
    Route::view('/reports/expense', 'property.agent.reports.expense.index')->name('reports.expense');
    Route::view('/reports/maintenance', 'property.agent.reports.maintenance.index')->name('reports.maintenance');
    Route::view('/reports/financial', 'property.agent.reports.financial.index')->name('reports.financial');
    Route::get('/reports/tenant/statements', [PropertyReportsController::class, 'tenantStatements'])->name('reports.tenant.statements');
    Route::get('/reports/tenant/rent-penalties', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'tenant_rent_penalties')->name('reports.tenant.rent_penalties');
    Route::get('/reports/tenant/de-allocation', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'tenant_de_allocation')->name('reports.tenant.de_allocation');
    Route::get('/reports/tenant/allocation', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'tenant_allocation')->name('reports.tenant.allocation');
    Route::get('/reports/tenant/deposits', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'tenant_deposits')->name('reports.tenant.deposits');
    Route::get('/reports/tenant/aging-balance', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'tenant_aging_balance')->name('reports.tenant.aging_balance');
    Route::get('/reports/tenant/statements-by-allocation', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'tenant_statements_by_allocation')->name('reports.tenant.statements_by_allocation');
    Route::get('/reports/landlord/statements', [PropertyReportsController::class, 'landlordStatements'])->name('reports.landlord.statements');
    Route::get('/reports/landlord/statements/expenses', [PropertyReportsController::class, 'landlordStatementExpenses'])->name('reports.landlord.statements.expenses');
    Route::get('/reports/landlord/detailed-statement', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_detailed_statement')->name('reports.landlord.detailed_statement');
    Route::get('/reports/landlord/balance-summary', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_balance_summary')->name('reports.landlord.balance_summary');
    Route::get('/reports/landlord/rental-income-commissions', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_rental_income_commissions')->name('reports.landlord.rental_income_commissions');
    Route::get('/reports/landlord/rent-collection', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_rent_collection')->name('reports.landlord.rent_collection');
    Route::get('/reports/landlord/property-statement', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_property_statement')->name('reports.landlord.property_statement');
    Route::get('/reports/expense/income-expenses-summary', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_income_expenses_summary')->name('reports.expense.income_expenses_summary');
    Route::get('/reports/expense/maintenance-expense', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_maintenance_expense')->name('reports.expense.maintenance_expense');
    Route::get('/reports/expense/utility-billing', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_utility_billing')->name('reports.expense.utility_billing');
    Route::get('/reports/expense/utility-aging', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_utility_aging')->name('reports.expense.utility_aging');
    Route::get('/reports/expense/vendor-expense-work', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_vendor_expense_work')->name('reports.expense.vendor_expense_work');
    Route::get('/reports/expense/cash-book', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_cash_book')->name('reports.expense.cash_book');
    Route::get('/reports/maintenance/history', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'maintenance_history')->name('reports.maintenance.history');
    Route::get('/reports/maintenance/cost', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'maintenance_cost')->name('reports.maintenance.cost');
    Route::get('/reports/maintenance/frequency', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'maintenance_frequency')->name('reports.maintenance.frequency');
    Route::get('/reports/maintenance/audit-trail', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'maintenance_audit_trail')->name('reports.maintenance.audit_trail');
    Route::get('/reports/maintenance/email-logs', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'maintenance_email_logs')->name('reports.maintenance.email_logs');
    Route::get('/reports/maintenance/login-logs', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'maintenance_login_logs')->name('reports.maintenance.login_logs');
    Route::get('/reports/financial/profit-loss-summary', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'financial_profit_loss_summary')->name('reports.financial.profit_loss_summary');
    Route::get('/reports/financial/profit-loss-comparison', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'financial_profit_loss_comparison')->name('reports.financial.profit_loss_comparison');
    Route::get('/reports/financial/profit-loss-department', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'financial_profit_loss_department')->name('reports.financial.profit_loss_department');
    Route::get('/reports/financial/profit-loss-months', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'financial_profit_loss_months')->name('reports.financial.profit_loss_months');
    Route::get('/reports/financial/manufacturing-account', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'financial_manufacturing_account')->name('reports.financial.manufacturing_account');
    Route::get('/reports/financial/balance-sheet-standard', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'financial_balance_sheet_standard')->name('reports.financial.balance_sheet_standard');
    Route::get('/reports/financial/balance-sheet-itemised', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'financial_balance_sheet_itemised')->name('reports.financial.balance_sheet_itemised');

    Route::get('/reports/export/{reportKey}', [PropertyReportsController::class, 'exportReportCsv'])
        ->where('reportKey', '[a-z0-9\_]+')
        ->name('reports.export.csv');

    Route::get('/properties/list', [PropertyPortfolioController::class, 'propertyList'])->name('properties.list');
    Route::get('/properties/list/export', [PropertyPortfolioController::class, 'propertyListExport'])->name('properties.list.export');
    Route::get('/properties/import/register', [PropertyPortfolioController::class, 'propertyRegisterImportForm'])
        ->middleware('property.permission:properties.manage')
        ->name('properties.register_import');
    Route::get('/properties/import/register/template.csv', [PropertyPortfolioController::class, 'propertyRegisterImportTemplate'])
        ->middleware('property.permission:properties.manage')
        ->name('properties.register_import.template');
    Route::post('/properties/import/register', [PropertyPortfolioController::class, 'propertyRegisterImportStore'])
        ->middleware('property.permission:properties.manage')
        ->name('properties.register_import.store');
    Route::post('/properties', [PropertyPortfolioController::class, 'storeProperty'])->middleware('property.permission:properties.manage')->name('properties.store');
    Route::post('/properties/create-json', [PropertyPortfolioController::class, 'storePropertyJson'])->middleware('property.permission:properties.manage')->name('properties.store_json');
    Route::get('/properties/{property}/edit', [PropertyPortfolioController::class, 'editProperty'])->whereNumber('property')->name('properties.edit');
    Route::patch('/properties/{property}', [PropertyPortfolioController::class, 'updateProperty'])->whereNumber('property')->middleware('property.permission:properties.manage')->name('properties.update');
    Route::delete('/properties/{property}', [PropertyPortfolioController::class, 'destroyProperty'])->whereNumber('property')->middleware('property.permission:properties.manage')->name('properties.destroy');
    Route::get('/properties/{property}/offboarding', [PropertyOffboardingController::class, 'show'])->whereNumber('property')->middleware('property.permission:properties.manage')->name('properties.offboarding');
    Route::post('/properties/{property}/offboarding/start', [PropertyOffboardingController::class, 'start'])->whereNumber('property')->middleware('property.permission:properties.manage')->name('properties.offboarding.start');
    Route::patch('/properties/{property}/offboarding/notes', [PropertyOffboardingController::class, 'updateNotes'])->whereNumber('property')->middleware('property.permission:properties.manage')->name('properties.offboarding.notes');
    Route::post('/properties/{property}/offboarding/archive', [PropertyOffboardingController::class, 'archive'])->whereNumber('property')->middleware('property.permission:property.offboarding.complete')->name('properties.offboarding.archive');
    Route::post('/properties/{property}/offboarding/restore', [PropertyOffboardingController::class, 'restore'])->whereNumber('property')->middleware('property.permission:property.archive.restore')->name('properties.offboarding.restore');
    Route::get('/properties/{property}/offboarding/handover-export', [PropertyOffboardingController::class, 'exportHandover'])->whereNumber('property')->middleware('property.permission:properties.manage')->name('properties.offboarding.handover_export');
    Route::post('/properties/{property}/offboarding/leases/{lease}/schedule-termination', [PropertyOffboardingController::class, 'scheduleLeaseTermination'])->whereNumber(['property', 'lease'])->middleware('property.permission:properties.manage')->name('properties.offboarding.schedule_lease');
    Route::post('/landlords/onboard', [PropertyPortfolioController::class, 'onboardLandlord'])->name('landlords.onboard');
    Route::post('/landlords/onboard-json', [PropertyPortfolioController::class, 'onboardLandlordJson'])->name('landlords.onboard_json');
    Route::post('/properties/landlords', [PropertyPortfolioController::class, 'attachLandlord'])->name('properties.landlords.attach');
    Route::post('/properties/landlords/detach', [PropertyPortfolioController::class, 'detachLandlord'])->name('properties.landlords.detach');
    Route::post('/properties/landlords/ownership', [PropertyPortfolioController::class, 'updateLandlordOwnership'])->name('properties.landlords.ownership');
    Route::get('/landlords', [PropertyPortfolioController::class, 'landlordsIndex'])->name('landlords.index');
    Route::get('/field-officers', [FieldOfficerController::class, 'index'])->name('field_officers.index');
    Route::get('/field-officers/create', [FieldOfficerController::class, 'create'])->middleware('property.permission:properties.manage')->name('field_officers.create');
    Route::post('/field-officers', [FieldOfficerController::class, 'store'])->middleware('property.permission:properties.manage')->name('field_officers.store');
    Route::get('/field-officers/{fieldOfficer}', [FieldOfficerController::class, 'show'])->whereNumber('fieldOfficer')->name('field_officers.show');
    Route::get('/field-officers/{fieldOfficer}/edit', [FieldOfficerController::class, 'edit'])->whereNumber('fieldOfficer')->middleware('property.permission:properties.manage')->name('field_officers.edit');
    Route::put('/field-officers/{fieldOfficer}', [FieldOfficerController::class, 'update'])->whereNumber('fieldOfficer')->middleware('property.permission:properties.manage')->name('field_officers.update');
    Route::post('/field-officers/{fieldOfficer}/properties/assign', [FieldOfficerController::class, 'assignProperty'])->whereNumber('fieldOfficer')->middleware('property.permission:properties.manage')->name('field_officers.properties.assign');
    Route::post('/field-officers/{fieldOfficer}/properties/detach', [FieldOfficerController::class, 'detachProperty'])->whereNumber('fieldOfficer')->middleware('property.permission:properties.manage')->name('field_officers.properties.detach');
    Route::get('/hr', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('hr'))->name('hr.index');
    Route::get('/hr/employees', [PropertyHrEmployeesController::class, 'index'])->name('hr.employees.index');
    Route::get('/hr/employees/create', [PropertyHrEmployeesController::class, 'create'])->middleware('property.permission:properties.manage')->name('hr.employees.create');
    Route::post('/hr/employees', [PropertyHrEmployeesController::class, 'store'])->middleware('property.permission:properties.manage')->name('hr.employees.store');
    Route::get('/hr/employees/{employee}', [PropertyHrEmployeesController::class, 'show'])->whereNumber('employee')->name('hr.employees.show');
    Route::get('/hr/employees/{employee}/edit', [PropertyHrEmployeesController::class, 'edit'])->whereNumber('employee')->middleware('property.permission:properties.manage')->name('hr.employees.edit');
    Route::put('/hr/employees/{employee}', [PropertyHrEmployeesController::class, 'update'])->whereNumber('employee')->middleware('property.permission:properties.manage')->name('hr.employees.update');
    Route::post('/hr/employees/{employee}/properties/assign', [PropertyHrEmployeesController::class, 'assignProperty'])->whereNumber('employee')->middleware('property.permission:properties.manage')->name('hr.employees.properties.assign');
    Route::post('/hr/employees/{employee}/properties/detach', [PropertyHrEmployeesController::class, 'detachProperty'])->whereNumber('employee')->middleware('property.permission:properties.manage')->name('hr.employees.properties.detach');
    Route::get('/hr/leaves', [PropertyHrLeavesController::class, 'index'])->name('hr.leaves.index');
    Route::get('/hr/leaves/create', [PropertyHrLeavesController::class, 'create'])->middleware('property.permission:properties.manage')->name('hr.leaves.create');
    Route::post('/hr/leaves', [PropertyHrLeavesController::class, 'store'])->middleware('property.permission:properties.manage')->name('hr.leaves.store');
    Route::post('/hr/leaves/{staffLeave}/status', [PropertyHrLeavesController::class, 'updateStatus'])->whereNumber('staffLeave')->middleware('property.permission:properties.manage')->name('hr.leaves.status');
    Route::get('/landlords/{landlord}', [PropertyPortfolioController::class, 'landlordsShow'])->whereNumber('landlord')->name('landlords.show');
    Route::get('/landlords/{landlord}/edit', [PropertyPortfolioController::class, 'editLandlord'])->whereNumber('landlord')->middleware('property.permission:properties.manage')->name('landlords.edit');
    Route::put('/landlords/{landlord}', [PropertyPortfolioController::class, 'updateLandlord'])->whereNumber('landlord')->middleware('property.permission:properties.manage')->name('landlords.update');
    Route::get('/landlords/{landlord}/statement', [PropertyPortfolioController::class, 'landlordsStatement'])->whereNumber('landlord')->name('landlords.statement');
    Route::post('/landlords/{landlord}/resend-portal-login', [PropertyPortfolioController::class, 'resendLandlordPortalLogin'])
        ->whereNumber('landlord')
        ->middleware('property.permission:properties.manage')
        ->name('landlords.resend_portal_login');
    Route::post('/landlords/{landlord}/impersonate', [PropertyPortfolioController::class, 'impersonateLandlord'])
        ->whereNumber('landlord')
        ->name('landlords.impersonate');
    Route::get('/properties/units', [PropertyPortfolioController::class, 'unitList'])->name('properties.units');
    Route::get('/properties/units/export', [PropertyPortfolioController::class, 'unitListExport'])->name('properties.units.export');
    Route::post('/units', [PropertyPortfolioController::class, 'storeUnit'])->middleware('property.permission:properties.manage')->name('units.store');
    Route::post('/units/create-json', [PropertyPortfolioController::class, 'storeUnitJson'])->middleware('property.permission:properties.manage')->name('units.store_json');
    Route::get('/units/{unit}/edit', [PropertyPortfolioController::class, 'editUnit'])->middleware('property.permission:properties.manage')->name('units.edit');
    Route::patch('/units/{unit}', [PropertyPortfolioController::class, 'updateUnit'])->middleware('property.permission:properties.manage')->name('units.update');
    Route::post('/units/{unit}/status', [PropertyPortfolioController::class, 'updateUnitStatus'])->middleware('property.permission:properties.manage')->name('units.status');
    Route::delete('/units/{unit}', [PropertyPortfolioController::class, 'destroyUnit'])->middleware('property.permission:properties.manage')->name('units.destroy');
    Route::get('/properties/occupancy', [PropertyPortfolioController::class, 'occupancy'])->name('properties.occupancy');
    Route::post('/properties/occupancy/bulk', [PropertyPortfolioController::class, 'occupancyBulkAction'])->middleware('property.permission:properties.manage')->name('properties.occupancy.bulk');
    Route::get('/properties/performance', [PropertyPortfolioController::class, 'propertyPerformance'])->name('properties.performance');
    Route::get('/properties/amenities', [PropertyAmenityController::class, 'index'])->name('properties.amenities');
    Route::post('/properties/amenities', [PropertyAmenityController::class, 'store'])->name('properties.amenities.store');
    Route::post('/properties/amenities/attach', [PropertyAmenityController::class, 'attach'])->name('properties.amenities.attach');
    Route::post('/properties/amenities/detach', [PropertyAmenityController::class, 'detach'])->name('properties.amenities.detach');
    Route::delete('/properties/amenities/{amenity}', [PropertyAmenityController::class, 'destroy'])->name('properties.amenities.destroy');
    Route::get('/properties/{property}', [PropertyPortfolioController::class, 'showProperty'])->whereNumber('property')->name('properties.show');
    Route::get('/properties', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('portfolio'))->name('properties.index');

    Route::get('/maintenance/requests', [PmMaintenanceWebController::class, 'requests'])->name('maintenance.requests');
    Route::get('/maintenance/requests/export', [PmMaintenanceWebController::class, 'requestsExport'])->name('maintenance.requests.export');
    Route::post('/maintenance/requests', [PmMaintenanceWebController::class, 'storeRequest'])->middleware('property.permission:maintenance.manage')->name('maintenance.requests.store');
    Route::get('/maintenance/requests/{requestItem}/edit', [PmMaintenanceWebController::class, 'editRequest'])->name('maintenance.requests.edit');
    Route::put('/maintenance/requests/{requestItem}', [PmMaintenanceWebController::class, 'updateRequest'])->middleware('property.permission:maintenance.manage')->name('maintenance.requests.update');
    Route::post('/maintenance/requests/{requestItem}/status', [PmMaintenanceWebController::class, 'updateRequestStatus'])->middleware('property.permission:maintenance.manage')->name('maintenance.requests.status');
    Route::get('/maintenance/jobs', [PmMaintenanceWebController::class, 'jobs'])->name('maintenance.jobs');
    Route::get('/maintenance/jobs/export', [PmMaintenanceWebController::class, 'jobsExport'])->name('maintenance.jobs.export');
    Route::post('/maintenance/jobs', [PmMaintenanceWebController::class, 'storeJob'])->middleware('property.permission:maintenance.manage')->name('maintenance.jobs.store');
    Route::get('/maintenance/jobs/{job}/edit', [PmMaintenanceWebController::class, 'editJob'])->name('maintenance.jobs.edit');
    Route::put('/maintenance/jobs/{job}', [PmMaintenanceWebController::class, 'updateJob'])->middleware('property.permission:maintenance.manage')->name('maintenance.jobs.update');
    Route::delete('/maintenance/jobs/{job}', [PmMaintenanceWebController::class, 'destroyJob'])->middleware('property.permission:maintenance.manage')->name('maintenance.jobs.destroy');
    Route::post('/maintenance/jobs/{job}/status', [PmMaintenanceWebController::class, 'updateJobStatus'])->middleware('property.permission:maintenance.manage')->name('maintenance.jobs.status');
    Route::get('/maintenance/history', [PmMaintenanceWebController::class, 'history'])->name('maintenance.history');
    Route::get('/maintenance/costs', [PmMaintenanceWebController::class, 'costs'])->name('maintenance.costs');
    Route::get('/maintenance/frequency', [PmMaintenanceWebController::class, 'frequency'])->name('maintenance.frequency');
    Route::get('/maintenance', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('maintenance'))->name('maintenance.index');

    Route::get('/vendors/directory', [PmVendorWebController::class, 'directory'])->name('vendors.directory');
    Route::get('/vendors/directory/export', [PmVendorWebController::class, 'directoryExport'])->name('vendors.directory.export');
    Route::post('/vendors', [PmVendorWebController::class, 'store'])->middleware('property.permission:vendors.manage')->name('vendors.store');
    Route::post('/vendors/create-json', [PmVendorWebController::class, 'storeJson'])->middleware('property.permission:vendors.manage')->name('vendors.store_json');
    Route::get('/vendors/{vendor}/edit', [PmVendorWebController::class, 'edit'])->name('vendors.edit');
    Route::put('/vendors/{vendor}', [PmVendorWebController::class, 'update'])->middleware('property.permission:vendors.manage')->name('vendors.update');
    Route::post('/vendors/{vendor}/status', [PmVendorWebController::class, 'updateStatus'])->middleware('property.permission:vendors.manage')->name('vendors.status');
    Route::get('/vendors/bidding/create', [PmVendorWebController::class, 'createBiddingRfqForm'])->name('vendors.bidding.create');
    Route::post('/vendors/bidding/rfq', [PmVendorWebController::class, 'storeBiddingRfq'])->middleware('property.permission:vendors.manage')->name('vendors.bidding.store');
    Route::get('/vendors/bidding', [PmVendorWebController::class, 'bidding'])->name('vendors.bidding');
    Route::get('/vendors/quotes', [PmVendorWebController::class, 'quotes'])->name('vendors.quotes');
    Route::post('/vendors/quotes/{job}/award', [PmVendorWebController::class, 'awardQuote'])->middleware('property.permission:vendors.manage')->name('vendors.quotes.award');
    Route::get('/vendors/performance', [PmVendorWebController::class, 'performance'])->name('vendors.performance');
    Route::get('/vendors/work-records', [PmVendorWebController::class, 'workRecords'])->name('vendors.work_records');
    Route::post('/vendors/{vendor}/jobs/{job}/mark-paid', [PmVendorWebController::class, 'markJobPaid'])->middleware('property.permission:vendors.manage')->name('vendors.jobs.mark_paid');
    Route::post('/vendors/{vendor}/pay-outstanding', [PmVendorWebController::class, 'payOutstanding'])->middleware('property.permission:vendors.manage')->name('vendors.pay_outstanding');
    Route::get('/vendors/{vendor}', [PmVendorWebController::class, 'show'])->name('vendors.show');
    Route::get('/vendors', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('vendors'))->name('vendors.index');

    Route::get('/financials/income-expenses', [FinancialsController::class, 'incomeExpenses'])->name('financials.income_expenses');
    Route::get('/financials/cash-flow', [FinancialsController::class, 'cashFlow'])->name('financials.cash_flow');
    Route::get('/financials/owner-balances', [FinancialsController::class, 'ownerBalances'])->name('financials.owner_balances');
    Route::get('/financials/commission', [FinancialsController::class, 'commission'])->name('financials.commission');
    Route::view('/financials', 'property.agent.financials.index')->name('financials.index');

    Route::get('/accounting', [PropertyAccountingController::class, 'index'])->name('accounting.index');
    Route::get('/accounting/entries', [PropertyAccountingController::class, 'entries'])->name('accounting.entries');
    Route::get('/accounting/chart-of-accounts', [PropertyAccountingController::class, 'chartOfAccounts'])->name('accounting.gl.chart_accounts');
    Route::post('/accounting/chart-of-accounts', [PropertyAccountingController::class, 'storeChartAccount'])->name('accounting.gl.chart_accounts.store');
    Route::post('/accounting/chart-of-accounts/{account}/disable', [PropertyAccountingController::class, 'disableChartAccount'])->name('accounting.gl.chart_accounts.disable');
    Route::post('/accounting/chart-of-accounts/{account}/clone', [PropertyAccountingController::class, 'cloneChartAccount'])->name('accounting.gl.chart_accounts.clone');
    Route::post('/accounting/chart-of-accounts/{account}/usage-default', [PropertyAccountingController::class, 'setDefaultUsage'])->name('accounting.gl.chart_accounts.usage_default');
    Route::get('/accounting/chart-of-accounts/export', [PropertyAccountingController::class, 'exportChartOfAccounts'])->name('accounting.gl.chart_accounts.export');
    Route::get('/accounting/journal-batches', [PropertyAccountingController::class, 'journalBatches'])->name('accounting.gl.journal_batches');
    Route::get('/accounting/journal-batches/{batch}/export', [PropertyAccountingController::class, 'exportJournalBatch'])->name('accounting.gl.journal_batches.export');
    Route::get('/accounting/receivables/accounts', [PropertyAccountingController::class, 'accountsReceivable'])->name('accounting.receivables.accounts');
    Route::get('/accounting/receivables/tenant-statements', [PropertyAccountingController::class, 'tenantStatements'])->name('accounting.receivables.tenant_statements');
    Route::get('/accounting/payables/landlord-payment-fees', [PropertyAccountingController::class, 'landlordPaymentFees'])->name('accounting.payables.landlord_payment_fees');
    Route::post('/accounting/payables/landlord-payment-fees/batch', [PropertyAccountingController::class, 'batchLandlordPaymentFees'])->name('accounting.payables.landlord_payment_fees.batch');
    Route::get('/accounting/payables/landlord-settlements', [PropertyAccountingController::class, 'landlordSettlements'])->name('accounting.payables.landlord_settlements');
    Route::post('/accounting/payables/landlord-settlements/payout', [PropertyAccountingController::class, 'storeLandlordSettlementPayout'])->name('accounting.payables.landlord_settlements.payout');
    Route::get('/accounting/payables/landlord-payables', [PropertyAccountingController::class, 'landlordPayables'])->name('accounting.payables.landlord_payables');
    Route::get('/accounting/payables/landlord-payouts', [PropertyAccountingController::class, 'landlordPayouts'])->name('accounting.payables.landlord_payouts');
    Route::post('/accounting/payables/landlord-payouts/{payout}/approve', [PropertyAccountingController::class, 'approveLandlordPayout'])->name('accounting.payables.landlord_payouts.approve');
    Route::post('/accounting/payables/landlord-payouts/{payout}/pay', [PropertyAccountingController::class, 'payLandlordPayout'])->name('accounting.payables.landlord_payouts.pay');
    Route::get('/accounting/payables/landlord-advances', [PropertyAccountingController::class, 'landlordAdvances'])->name('accounting.payables.landlord_advances');
    Route::post('/accounting/payables/landlord-advances', [PropertyAccountingController::class, 'storeLandlordAdvance'])->name('accounting.payables.landlord_advances.store');
    Route::post('/accounting/payables/landlord-advances/schedule', [PropertyAccountingController::class, 'updateLandlordAgreedPaySchedule'])->name('accounting.payables.landlord_advances.schedule');
    Route::post('/accounting/payables/landlord-advances/{item}/recover', [PropertyAccountingController::class, 'markLandlordAdvanceRecovered'])->whereNumber('item')->name('accounting.payables.landlord_advances.recover');
    Route::post('/accounting/payables/landlord-advances/{item}/write-off', [PropertyAccountingController::class, 'writeOffLandlordAdvance'])->whereNumber('item')->name('accounting.payables.landlord_advances.write_off');
    Route::get('/accounting/payables/landlord-remittances', [\App\Http\Controllers\Property\Agent\LandlordRemittanceAgentController::class, 'index'])->name('accounting.payables.landlord_remittances');
    Route::post('/accounting/payables/landlord-remittances/{remittance}/acknowledge', [\App\Http\Controllers\Property\Agent\LandlordRemittanceAgentController::class, 'acknowledge'])->name('accounting.payables.landlord_remittances.acknowledge');
    Route::post('/accounting/payables/landlord-remittances/{remittance}/paid', [\App\Http\Controllers\Property\Agent\LandlordRemittanceAgentController::class, 'markPaid'])->name('accounting.payables.landlord_remittances.paid');
    Route::post('/accounting/payables/landlord-remittances/{remittance}/cancel', [\App\Http\Controllers\Property\Agent\LandlordRemittanceAgentController::class, 'cancel'])->name('accounting.payables.landlord_remittances.cancel');
    Route::get('/accounting/payables/accounts-payable', [PropertyAccountingController::class, 'accountsPayable'])->name('accounting.payables.accounts_payable');
    Route::get('/accounting/cash-bank/reconciliation', [PropertyAccountingController::class, 'bankReconciliation'])->name('accounting.cash_bank.reconciliation');
    Route::get('/accounting/entries/export', [PropertyAccountingController::class, 'exportEntriesCsv'])->name('accounting.entries.export');
    Route::get('/accounting/entries/{batch}', [PropertyAccountingController::class, 'showEntry'])->name('accounting.entries.show');
    Route::post('/accounting/entries', [PropertyAccountingController::class, 'storeEntry'])->middleware('property.permission:accounting.entries.manage')->name('accounting.entries.store');
    Route::post('/accounting/entries/{entry}/reverse', [PropertyAccountingController::class, 'reverseEntry'])->middleware('property.permission:accounting.entries.manage')->name('accounting.entries.reverse');
    Route::post('/accounting/entries/bulk', [PropertyAccountingController::class, 'bulkEntries'])->middleware('property.permission:accounting.entries.manage')->name('accounting.entries.bulk');
    Route::post('/accounting/settings/account-map', [PropertyAccountingController::class, 'saveAccountMap'])->name('accounting.settings.account_map.save');
    Route::get('/accounting/audit-trail', [PropertyAccountingController::class, 'auditTrail'])->name('accounting.audit_trail');
    Route::get('/accounting/audit-trail/{batch}', [PropertyAccountingController::class, 'auditTrailShow'])->name('accounting.audit_trail.show');
    Route::get('/accounting/audit-trail/export', [PropertyAccountingController::class, 'exportAuditTrailCsv'])->name('accounting.audit_trail.export');
    Route::get('/accounting/payroll', [PropertyAccountingController::class, 'payroll'])->name('accounting.payroll');
    Route::post('/accounting/payroll', [PropertyAccountingController::class, 'payrollStore'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.store');
    Route::post('/accounting/payroll/employee', [PropertyAccountingController::class, 'payrollEmployeeStore'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.employee.store');
    Route::get('/accounting/payroll/payslips', [PropertyAccountingController::class, 'payrollPayslips'])->name('accounting.payroll.payslips');
    Route::get('/accounting/payroll/payslips/export', [PropertyAccountingController::class, 'exportPayrollPayslipsCsv'])->name('accounting.payroll.payslips.export');
    Route::get('/accounting/payroll/payslips/{reference}', [PropertyAccountingController::class, 'payrollPayslipShow'])->name('accounting.payroll.payslips.show');
    Route::get('/accounting/payroll/settings', [PropertyAccountingController::class, 'payrollSettings'])->name('accounting.payroll.settings');
    Route::post('/accounting/payroll/settings', [PropertyAccountingController::class, 'payrollSettingsSave'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.settings.save');
    Route::get('/accounting/payroll/{period}', [PropertyAccountingController::class, 'payrollShow'])->name('accounting.payroll.show');
    Route::post('/accounting/payroll/{period}/approve', [PropertyAccountingController::class, 'payrollApprove'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.approve');
    Route::post('/accounting/payroll/{period}/post', [PropertyAccountingController::class, 'payrollPost'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.post');
    Route::post('/accounting/payroll/{period}/reverse', [PropertyAccountingController::class, 'payrollReverse'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.reverse');
    Route::post('/accounting/payroll/{period}/payslips/email-all', [PropertyAccountingController::class, 'payrollPayslipsEmailAll'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.payslips.email_all');
    Route::get('/accounting/payroll/{period}/export', [PropertyAccountingController::class, 'payrollExport'])->name('accounting.payroll.export');
    Route::get('/accounting/payroll/{period}/lines/{line}/payslip', [PropertyAccountingController::class, 'payrollLinePayslipShow'])->name('accounting.payroll.lines.payslip.show');
    Route::get('/accounting/payroll/{period}/lines/{line}/payslip/download', [PropertyAccountingController::class, 'payrollLinePayslipDownload'])->name('accounting.payroll.lines.payslip.download');
    Route::post('/accounting/payroll/{period}/lines/{line}/payslip/email', [PropertyAccountingController::class, 'payrollLinePayslipEmail'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.lines.payslip.email');
    Route::post('/accounting/payroll/{period}/lines/{line}/payment', [PropertyAccountingController::class, 'payrollLinePaymentUpdate'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.lines.payment.update');
    Route::get('/accounting/reports/trial-balance', [PropertyAccountingController::class, 'trialBalance'])->name('accounting.reports.trial_balance');
    Route::get('/accounting/reports/trial-balance/export', [PropertyAccountingController::class, 'exportTrialBalanceCsv'])->name('accounting.reports.trial_balance.export');
    Route::get('/accounting/reports/income-statement', [PropertyAccountingController::class, 'incomeStatement'])->name('accounting.reports.income_statement');
    Route::get('/accounting/reports/income-statement/export', [PropertyAccountingController::class, 'exportIncomeStatementCsv'])->name('accounting.reports.income_statement.export');
    Route::get('/accounting/reports/balance-sheet', [PropertyAccountingController::class, 'balanceSheet'])->name('accounting.reports.balance_sheet');
    Route::get('/accounting/reports/aged-receivables', [PropertyAccountingController::class, 'agedReceivables'])->name('accounting.reports.aged_receivables');
    Route::get('/accounting/reports/aged-payables', [PropertyAccountingController::class, 'agedPayables'])->name('accounting.reports.aged_payables');
    Route::get('/accounting/reports/deposit-liability', [PropertyAccountingController::class, 'depositLiabilityReport'])->name('accounting.reports.deposit_liability');
    Route::get('/accounting/reports/cash-book', [PropertyAccountingController::class, 'cashBook'])->name('accounting.reports.cash_book');
    Route::get('/accounting/reports/cash-book/export', [PropertyAccountingController::class, 'exportCashBookCsv'])->name('accounting.reports.cash_book.export');
    Route::get('/accounting/controls/reversals', [PropertyAccountingController::class, 'reversals'])->name('accounting.controls.reversals');
    Route::get('/accounting/finance-diagnostics', [FinanceDiagnosticsController::class, 'index'])->name('accounting.finance_diagnostics');
    Route::post('/accounting/finance-diagnostics/refresh-invoice-statuses', [FinanceDiagnosticsController::class, 'refreshInvoiceStatuses'])->name('accounting.finance_diagnostics.refresh_invoice_statuses');
    Route::get('/accounting/reconciliation', [AccountingReconciliationController::class, 'index'])->name('accounting.reconciliation');
    Route::get('/accounting/finance-integrity', [FinanceIntegrityDashboardController::class, 'index'])->name('accounting.finance_integrity');
    Route::get('/accounting/financial-reconciliation', [FinancialReconciliationController::class, 'index'])->name('accounting.financial_reconciliation');
    Route::get('/accounting/controls/periods', [PropertyAccountingController::class, 'periods'])->name('accounting.controls.periods');
    Route::post('/accounting/controls/periods/initialize', [PropertyAccountingController::class, 'initializePeriods'])->name('accounting.controls.periods.initialize');
    Route::post('/accounting/controls/periods/{period}/status', [PropertyAccountingController::class, 'updatePeriodStatus'])->name('accounting.controls.periods.status');
    Route::get('/accounting/settings/account-mapping', [PropertyAccountingController::class, 'accountMapping'])->name('accounting.settings.account_mapping');
    Route::get('/accounting/settings/financial', [PropertyAccountingController::class, 'financialSettings'])->name('accounting.settings.financial');

    Route::get('/performance/collection-rate', [PerformanceWorkspaceController::class, 'collectionRate'])->name('performance.collection_rate');
    Route::get('/performance/vacancy', [PerformanceWorkspaceController::class, 'vacancy'])->name('performance.vacancy');
    Route::get('/performance/arrears-trends', [PerformanceWorkspaceController::class, 'arrearsTrends'])->name('performance.arrears_trends');
    Route::get('/performance/maintenance-trends', [PerformanceWorkspaceController::class, 'maintenanceTrends'])->name('performance.maintenance_trends');
    Route::get('/performance/tenant-reliability', [PerformanceWorkspaceController::class, 'tenantReliability'])->name('performance.tenant_reliability');
    Route::get('/performance', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('analytics'))->name('performance.index');

    Route::get('/notifications', [PropertyCommunicationsWebController::class, 'notifications'])->name('notifications');
    Route::get('/notifications/export', [PropertyCommunicationsWebController::class, 'notificationsExport'])->name('notifications.export');
    Route::post('/notifications/bulk', [PropertyCommunicationsWebController::class, 'notificationsBulk'])->name('notifications.bulk');
    Route::post('/notifications/mark-all-read', [PropertyCommunicationsWebController::class, 'notificationsMarkAllRead'])->name('notifications.mark_all_read');
    Route::get('/notifications/{log}', [PropertyCommunicationsWebController::class, 'showNotification'])->name('notifications.show');
    Route::get('/communications/messages', [PropertyCommunicationsWebController::class, 'messages'])->name('communications.messages');
    Route::get('/communications/messages/compose-context', [PropertyCommunicationsWebController::class, 'messagesComposeContext'])->middleware('property.permission:communications.manage')->name('communications.messages.compose_context');
    Route::get('/communications/messages/export', [PropertyCommunicationsWebController::class, 'messagesExport'])->name('communications.messages.export');
    Route::post('/communications/messages/bulk', [PropertyCommunicationsWebController::class, 'messagesBulk'])->middleware('property.permission:communications.manage')->name('communications.messages.bulk');
    Route::post('/communications/sms-topup', [PropertyCommunicationsWebController::class, 'smsWalletTopup'])->middleware('property.permission:communications.manage')->name('communications.sms_topup');
    Route::get('/communications/sms/balance', [PropertyCommunicationsWebController::class, 'smsBalanceJson'])->name('communications.sms_balance');
    Route::get('/communications/sms/topup-status', [PropertyCommunicationsWebController::class, 'smsTopupStatusJson'])->middleware('property.permission:communications.manage')->name('communications.sms_topup_status');
    Route::get('/communications/sms/provider', [PropertyCommunicationsWebController::class, 'smsProvider'])->name('communications.sms_provider');
    Route::post('/communications/messages/{log}/resend', [PropertyCommunicationsWebController::class, 'resendMessage'])->middleware('property.permission:communications.manage')->name('communications.messages.resend');
    Route::get('/communications/messages/{log}', [PropertyCommunicationsWebController::class, 'showMessage'])->name('communications.messages.show');
    Route::post('/communications/messages', [PropertyCommunicationsWebController::class, 'logMessage'])->middleware('property.permission:communications.manage')->name('communications.messages.store');
    Route::get('/communications/bulk', [PropertyCommunicationsWebController::class, 'bulk'])->name('communications.bulk');
    Route::get('/communications/bulk/export', [PropertyCommunicationsWebController::class, 'bulkExport'])->name('communications.bulk.export');
    Route::post('/communications/bulk', [PropertyCommunicationsWebController::class, 'logBulk'])->middleware('property.permission:communications.manage')->name('communications.bulk.store');
    Route::get('/communications/recipients', [PropertyCommunicationsWebController::class, 'recipients'])->middleware('property.permission:communications.manage')->name('communications.recipients');
    Route::get('/communications/templates', [PropertyCommunicationsWebController::class, 'templates'])->name('communications.templates');
    Route::post('/communications/templates', [PropertyCommunicationsWebController::class, 'storeTemplate'])->middleware('property.permission:communications.manage')->name('communications.templates.store');
    Route::delete('/communications/templates/{template}', [PropertyCommunicationsWebController::class, 'destroyTemplate'])->middleware('property.permission:communications.manage')->name('communications.templates.destroy');
    Route::get('/communications/rent-templates', [PropertyCommunicationsWebController::class, 'rentTemplates'])->name('communications.rent_templates');
    Route::post('/communications/rent-templates', [PropertyCommunicationsWebController::class, 'saveRentTemplateMessages'])->middleware('property.permission:communications.manage')->name('communications.rent_templates.store');
    Route::post('/communications/rent-templates/preview', [PropertyCommunicationsWebController::class, 'previewRentTemplatesJson'])->name('communications.rent_templates.preview');
    Route::get('/communications/conversations', [PropertyCommunicationsWebController::class, 'conversationsPage'])->middleware('property.permission:communications.manage')->name('communications.conversations');
    Route::get('/communications/conversations-data', [PropertyCommunicationsWebController::class, 'conversations'])->middleware('property.permission:communications.manage')->name('communications.conversations.data');
    Route::get('/communications/conversations/{conversation}', [PropertyCommunicationsWebController::class, 'showConversation'])->middleware('property.permission:communications.manage')->name('communications.conversations.show');
    Route::post('/communications/conversations/{conversation}/reply', [PropertyCommunicationsWebController::class, 'replyConversation'])->middleware('property.permission:communications.manage')->name('communications.conversations.reply');
    Route::get('/communications', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('communications'))->name('communications.index');

    Route::get('/listings/vacant/{property_unit}/publish-panel', [AgentPublicListingController::class, 'publishPanel'])->name('listings.publish-panel');
    Route::get('/listings/create', [AgentPublicListingController::class, 'create'])->name('listings.create');
    Route::post('/listings/start', [AgentPublicListingController::class, 'start'])->middleware('property.permission:listings.manage')->name('listings.start');
    Route::get('/listings/vacant', [AgentPublicListingController::class, 'index'])->name('listings.vacant');
    Route::get('/listings/vacant/{property_unit}/public', [AgentPublicListingController::class, 'edit'])->name('listings.vacant.public.edit');
    Route::patch('/listings/vacant/{property_unit}/public', [AgentPublicListingController::class, 'update'])->middleware('property.permission:listings.manage')->name('listings.vacant.public.update');
    Route::post('/listings/vacant/{property_unit}/public/photos', [AgentPublicListingController::class, 'storePhotos'])->middleware('property.permission:listings.manage')->name('listings.vacant.public.photos.store');
    Route::post('/listings/vacant/{property_unit}/public/photos/{public_image}/main', [AgentPublicListingController::class, 'makePrimaryPhoto'])
        ->whereNumber('public_image')
        ->middleware('property.permission:listings.manage')
        ->name('listings.vacant.public.photos.main');
    Route::delete('/listings/vacant/{property_unit}/public/photos/{public_image}', [AgentPublicListingController::class, 'destroyPhoto'])
        ->whereNumber('public_image')
        ->middleware('property.permission:listings.manage')
        ->name('listings.vacant.public.photos.destroy');
    Route::get('/listings/ads', [AgentPublicListingController::class, 'ads'])->name('listings.ads');
    Route::get('/listings/leads', [PropertyListingsPipelineController::class, 'leads'])->name('listings.leads');
    Route::get('/listings/leads/export', [PropertyListingsPipelineController::class, 'leadsExport'])->name('listings.leads.export');
    Route::post('/listings/leads', [PropertyListingsPipelineController::class, 'storeLead'])->middleware('property.permission:listings.manage')->name('listings.leads.store');
    Route::patch('/listings/leads/{lead}', [PropertyListingsPipelineController::class, 'updateLeadStage'])->middleware('property.permission:listings.manage')->name('listings.leads.update');
    Route::get('/listings/applications', [PropertyListingsPipelineController::class, 'applications'])->name('listings.applications');
    Route::get('/listings/applications/export', [PropertyListingsPipelineController::class, 'applicationsExport'])->name('listings.applications.export');
    Route::post('/listings/applications', [PropertyListingsPipelineController::class, 'storeApplication'])->middleware('property.permission:listings.manage')->name('listings.applications.store');
    Route::get('/listings/applications/{application}', [PropertyListingsPipelineController::class, 'showApplication'])->name('listings.applications.show');
    Route::post('/listings/applications/{application}/message', [PropertyListingsPipelineController::class, 'sendApplicationMessage'])
        ->middleware('property.permission:communications.manage')
        ->name('listings.applications.message');
    Route::patch('/listings/applications/{application}', [PropertyListingsPipelineController::class, 'updateApplicationStatus'])->middleware('property.permission:listings.manage')->name('listings.applications.update');
    Route::get('/listings', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('listings'))->name('listings.index');

    Route::get('/settings/roles', [PropertySettingsWebController::class, 'roles'])->middleware('property.permission:team.users.manage')->name('settings.roles');
    Route::get('/settings/permissions', [PropertySettingsWebController::class, 'permissions'])->middleware('property.permission:settings.access.manage')->name('settings.permissions');
    Route::get('/settings/activity-log', [PropertyActivityLogController::class, 'index'])->name('settings.activity_log');
    Route::get('/settings/team-users/create', [PropertyTeamUserController::class, 'create'])->middleware('property.permission:team.users.manage')->name('settings.team_users.create');
    Route::post('/settings/team-users', [PropertyTeamUserController::class, 'store'])->middleware('property.permission:team.users.manage')->name('settings.team_users.store');
    Route::get('/settings/commission', [PropertySettingsStoreWebController::class, 'commission'])->name('settings.commission');
    Route::post('/settings/commission', [PropertySettingsStoreWebController::class, 'storeCommission'])->middleware('property.permission:settings.manage')->name('settings.commission.store');
    Route::get('/settings/payments', [PropertySettingsStoreWebController::class, 'payments'])->name('settings.payments');
    Route::post('/settings/payments', [PropertySettingsStoreWebController::class, 'storePayments'])->middleware('property.permission:settings.manage')->name('settings.payments.store');
    Route::get('/settings/branding', [PropertySettingsStoreWebController::class, 'branding'])->name('settings.branding');
    Route::post('/settings/branding', [PropertySettingsStoreWebController::class, 'storeBranding'])->middleware('property.permission:settings.manage')->name('settings.branding.store');

    // Per-agent SMS forwarder token. Self-service: each agent sees and
    // manages only their own tokens. No permission gate beyond being a
    // property portal "agent" because the controller already scopes by
    // auth()->id().
    Route::get('/settings/my-forwarder', [PmForwarderTokenController::class, 'index'])->name('settings.forwarder');
    Route::post('/settings/my-forwarder', [PmForwarderTokenController::class, 'store'])->name('settings.forwarder.store');
    Route::post('/settings/my-forwarder/{pmForwarderToken}/revoke', [PmForwarderTokenController::class, 'revoke'])->name('settings.forwarder.revoke');
    Route::get('/settings/rules', [PropertySettingsStoreWebController::class, 'rules'])->name('settings.rules');
    Route::post('/settings/rules', [PropertySettingsStoreWebController::class, 'storeRules'])->middleware('property.permission:settings.manage')->name('settings.rules.store');
    Route::get('/settings/deposits', [PropertySettingsStoreWebController::class, 'deposits'])->name('settings.deposits');
    Route::post('/settings/deposits', [PropertySettingsStoreWebController::class, 'storeDeposits'])->middleware('property.permission:settings.manage')->name('settings.deposits.store');
    Route::get('/settings/expenses', [PropertySettingsStoreWebController::class, 'expenses'])->name('settings.expenses');
    Route::post('/settings/expenses', [PropertySettingsStoreWebController::class, 'storeExpenses'])->middleware('property.permission:settings.manage')->name('settings.expenses.store');
    Route::get('/settings/system-setup', [PropertySettingsStoreWebController::class, 'systemSetup'])->middleware('property.permission:settings.manage')->name('settings.system_setup');
    Route::get('/settings/system-setup/forms', [PropertySettingsStoreWebController::class, 'systemSetupForms'])->middleware('property.permission:settings.manage')->name('settings.system_setup.forms');
    Route::post('/settings/system-setup/forms', [PropertySettingsStoreWebController::class, 'storeSystemSetupForms'])->middleware('property.permission:settings.manage')->name('settings.system_setup.forms.store');
    Route::get('/settings/system-setup/property-onboarding-fields', [PropertySettingsStoreWebController::class, 'systemSetupPropertyOnboardingFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.property_onboarding_fields');
    Route::post('/settings/system-setup/property-onboarding-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupPropertyOnboardingFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.property_onboarding_fields.store');
    Route::get('/settings/system-setup/unit-fields', [PropertySettingsStoreWebController::class, 'systemSetupUnitFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.unit_fields');
    Route::post('/settings/system-setup/unit-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupUnitFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.unit_fields.store');
    Route::get('/settings/system-setup/amenity-fields', [PropertySettingsStoreWebController::class, 'systemSetupAmenityFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.amenity_fields');
    Route::post('/settings/system-setup/amenity-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupAmenityFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.amenity_fields.store');
    Route::get('/settings/system-setup/landlord-fields', [PropertySettingsStoreWebController::class, 'systemSetupLandlordFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.landlord_fields');
    Route::post('/settings/system-setup/landlord-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupLandlordFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.landlord_fields.store');
    Route::get('/settings/system-setup/lead-fields', [PropertySettingsStoreWebController::class, 'systemSetupLeadFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.lead_fields');
    Route::post('/settings/system-setup/lead-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupLeadFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.lead_fields.store');
    Route::get('/settings/system-setup/rental-application-fields', [PropertySettingsStoreWebController::class, 'systemSetupRentalApplicationFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.rental_application_fields');
    Route::post('/settings/system-setup/rental-application-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupRentalApplicationFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.rental_application_fields.store');
    Route::get('/settings/system-setup/tenant-fields', [PropertySettingsStoreWebController::class, 'systemSetupTenantFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.tenant_fields');
    Route::post('/settings/system-setup/tenant-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupTenantFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.tenant_fields.store');
    Route::get('/settings/system-setup/lease-fields', [PropertySettingsStoreWebController::class, 'systemSetupLeaseFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.lease_fields');
    Route::post('/settings/system-setup/lease-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupLeaseFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.lease_fields.store');
    Route::get('/settings/system-setup/maintenance-fields', [PropertySettingsStoreWebController::class, 'systemSetupMaintenanceFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.maintenance_fields');
    Route::post('/settings/system-setup/maintenance-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupMaintenanceFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.maintenance_fields.store');
    Route::get('/settings/system-setup/vendor-fields', [PropertySettingsStoreWebController::class, 'systemSetupVendorFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.vendor_fields');
    Route::post('/settings/system-setup/vendor-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupVendorFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.vendor_fields.store');
    Route::get('/settings/system-setup/invoice-fields', [PropertySettingsStoreWebController::class, 'systemSetupInvoiceFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.invoice_fields');
    Route::post('/settings/system-setup/invoice-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupInvoiceFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.invoice_fields.store');
    Route::get('/settings/system-setup/tenant-notice-fields', [PropertySettingsStoreWebController::class, 'systemSetupTenantNoticeFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.tenant_notice_fields');
    Route::post('/settings/system-setup/tenant-notice-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupTenantNoticeFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.tenant_notice_fields.store');
    Route::get('/settings/system-setup/movement-fields', [PropertySettingsStoreWebController::class, 'systemSetupMovementFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.movement_fields');
    Route::post('/settings/system-setup/movement-fields', [PropertySettingsStoreWebController::class, 'storeSystemSetupMovementFields'])->middleware('property.permission:settings.manage')->name('settings.system_setup.movement_fields.store');
    Route::get('/settings/system-setup/workflows', [PropertySettingsStoreWebController::class, 'systemSetupWorkflows'])->middleware('property.permission:settings.manage')->name('settings.system_setup.workflows');
    Route::post('/settings/system-setup/workflows', [PropertySettingsStoreWebController::class, 'storeSystemSetupWorkflows'])->middleware('property.permission:settings.manage')->name('settings.system_setup.workflows.store');
    Route::get('/settings/system-setup/templates', [PropertySettingsStoreWebController::class, 'systemSetupTemplates'])->middleware('property.permission:settings.manage')->name('settings.system_setup.templates');
    Route::post('/settings/system-setup/templates', [PropertySettingsStoreWebController::class, 'storeSystemSetupTemplates'])->middleware('property.permission:settings.manage')->name('settings.system_setup.templates.store');
    Route::get('/settings/system-setup/access', [PropertySettingsStoreWebController::class, 'systemSetupAccess'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access');
    Route::post('/settings/system-setup/access/roles', [PropertySettingsStoreWebController::class, 'storeSystemSetupRole'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.roles.store');
    Route::post('/settings/system-setup/access/roles/clone', [PropertySettingsStoreWebController::class, 'storeSystemSetupRoleClone'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.roles.clone');
    Route::post('/settings/system-setup/access/permissions', [PropertySettingsStoreWebController::class, 'storeSystemSetupPermission'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.permissions.store');
    Route::patch('/settings/system-setup/access/permissions/{pmPermission}', [PropertySettingsStoreWebController::class, 'updateSystemSetupPermission'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.permissions.update');
    Route::delete('/settings/system-setup/access/permissions/{pmPermission}', [PropertySettingsStoreWebController::class, 'destroySystemSetupPermission'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.permissions.destroy');
    Route::post('/settings/system-setup/access/roles/{pmRole}/permissions', [PropertySettingsStoreWebController::class, 'storeSystemSetupRolePermissions'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.roles.permissions.store');
    Route::post('/settings/system-setup/access/matrix', [PropertySettingsStoreWebController::class, 'storeSystemSetupAccessMatrix'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.matrix.store');
    Route::post('/settings/system-setup/access/users/{user}/roles', [PropertySettingsStoreWebController::class, 'storeSystemSetupUserRoles'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.users.roles.store');
    Route::post('/settings/system-setup/access/users/{user}/permissions', [PropertySettingsStoreWebController::class, 'storeSystemSetupUserPermissions'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.users.permissions.store');
    Route::get('/settings', fn () => PropertyWorkspaceTabs::redirectToDefaultEntry('settings'))->name('settings.index');

    Route::get('/advisor', [PropertyAdvisorWebController::class, 'show'])->name('advisor');
    Route::post('/advisor/ask', [PropertyAdvisorWebController::class, 'ask'])->name('advisor.ask');

    Route::get('/quick-action', function () {
        return redirect()
            ->route('property.dashboard')
            ->with('success', 'That address is for form submissions only. Use an action button on the page you came from.');
    });
    Route::post('/quick-action', [PropertyPortalQuickActionController::class, 'storeAgent'])->name('quick_action.store');

    Route::get('/geo/kenya-addresses', [PropertyGeoController::class, 'suggestKenyaAddresses'])
        ->name('geo.kenya_addresses');
    Route::get('/exports/maintenance-costs', [PropertyDataExportController::class, 'maintenanceCosts'])->name('exports.maintenance_costs');
    Route::get('/exports/performance-snapshot', [PropertyDataExportController::class, 'performanceSnapshot'])->name('exports.performance_snapshot');
    Route::get('/exports/income-expenses-summary', [PropertyDataExportController::class, 'incomeExpensesSummary'])->name('exports.income_expenses_summary');
});
