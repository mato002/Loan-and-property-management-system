<?php

use App\Http\Controllers\Property\Agent\AgentPublicListingController;
use App\Http\Controllers\Property\Agent\AgentWorkspaceFormController;
use App\Http\Controllers\Property\Agent\DashboardController;
use App\Http\Controllers\Property\Agent\FinancialsController;
use App\Http\Controllers\Property\Agent\PerformanceWorkspaceController;
use App\Http\Controllers\Property\Agent\PmInvoiceController;
use App\Http\Controllers\Property\Agent\PmLeaseWebController;
use App\Http\Controllers\Property\Agent\PmMaintenanceWebController;
use App\Http\Controllers\Property\Agent\PmPaymentController;
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
use App\Http\Controllers\Property\Agent\PropertySettingsWebController;
use App\Http\Controllers\Property\Agent\PropertyTenantsOpsWebController;
use App\Http\Controllers\Property\Agent\PropertyUtilityChargeController;
use App\Http\Controllers\Property\Agent\PropertyReportsController;
use App\Http\Controllers\Property\Agent\RevenueController;
use App\Http\Controllers\Property\Agent\EquitySyncController;
use App\Http\Controllers\Property\Landlord\LandlordPortalController;
use App\Http\Controllers\Property\PropertyPortalQuickActionController;
use App\Http\Controllers\Property\PropertyGeoController;
use App\Http\Controllers\Property\Tenant\TenantPortalController;
use App\Http\Controllers\Property\Tenant\TenantWorkspaceFormController;
use Illuminate\Support\Facades\Route;

// Property portal is used by tenants/landlords who may not have verified emails.
// Keep auth + module access + active system checks, but do not block by `verified`.
Route::middleware(['auth', 'module.access:property', 'property.system'])->group(function () {

    Route::middleware(['property.portal:agent'])->prefix('property')->name('property.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'commandCenter'])->name('dashboard');

        Route::get('/workspace/forms/{form}', [AgentWorkspaceFormController::class, 'show'])
            ->where('form', '[a-z0-9\-]+')
            ->name('workspace.form.show');
        Route::post('/workspace/forms/{form}', [AgentWorkspaceFormController::class, 'store'])
            ->where('form', '[a-z0-9\-]+')
            ->name('workspace.form.store');

        Route::get('/revenue/rent-roll', [RevenueController::class, 'rentRoll'])->name('revenue.rent_roll');
        Route::get('/revenue/arrears', [RevenueController::class, 'arrears'])->name('revenue.arrears');
        Route::get('/revenue/invoices', [PmInvoiceController::class, 'invoices'])->name('revenue.invoices');
        Route::post('/revenue/invoices', [PmInvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/revenue/penalties', [RevenueController::class, 'penalties'])->name('revenue.penalties');
        Route::post('/revenue/penalties', [RevenueController::class, 'storePenaltyRule'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.penalties.store');
        Route::delete('/revenue/penalties/{penalty_rule}', [RevenueController::class, 'destroyPenaltyRule'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.penalties.destroy');
        Route::get('/revenue/payments', [PmPaymentController::class, 'payments'])->name('revenue.payments');
        Route::post('/revenue/payments', [PmPaymentController::class, 'store'])->middleware('property.permission:payments.record')->name('payments.store');
        Route::patch('/revenue/payments/{payment}/settle', [PmPaymentController::class, 'settle'])->middleware('property.permission:payments.settle')->name('payments.settle');
        Route::get('/revenue/receipts', [RevenueController::class, 'receipts'])->name('revenue.receipts');
        Route::get('/revenue/utilities-charges', [PropertyUtilityChargeController::class, 'index'])->name('revenue.utilities');
        Route::post('/revenue/utilities-charges', [PropertyUtilityChargeController::class, 'store'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.store');
        Route::post('/revenue/utilities-charges/water-readings', [PropertyUtilityChargeController::class, 'storeWaterReading'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_readings.store');
        Route::post('/revenue/utilities-charges/water-invoices', [PropertyUtilityChargeController::class, 'generateWaterInvoices'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.water_invoices.generate');
        Route::post('/revenue/utilities-charges/water-penalties/apply', [PropertyUtilityChargeController::class, 'applyWaterPenalties'])->middleware('property.permission:revenue.penalties.manage')->name('revenue.utilities.water_penalties.apply');
        Route::delete('/revenue/utilities-charges/{charge}', [PropertyUtilityChargeController::class, 'destroy'])->middleware('property.permission:revenue.utilities.manage')->name('revenue.utilities.destroy');
        Route::get('/revenue/equity/sync-status', [EquitySyncController::class, 'syncStatus'])
            ->middleware('property.permission:payments.settle')
            ->name('equity.sync_status');
        Route::post('/revenue/equity/sync-status/sync', [EquitySyncController::class, 'triggerSync'])
            ->middleware('property.permission:payments.settle')
            ->name('equity.sync_status.sync');
        Route::get('/revenue/equity/unmatched', [EquitySyncController::class, 'unmatchedPayments'])
            ->middleware('property.permission:payments.settle')
            ->name('equity.unmatched');
        Route::get('/revenue/equity/all', [EquitySyncController::class, 'allPayments'])
            ->middleware('property.permission:payments.settle')
            ->name('equity.all');
        Route::view('/revenue', 'property.agent.revenue.index')->name('revenue.index');

        Route::get('/tenants/directory', [PmTenantDirectoryController::class, 'directory'])->name('tenants.directory');
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
        Route::get('/tenants/{tenant}', [PmTenantDirectoryController::class, 'show'])->name('tenants.show');
        Route::get('/tenants/{tenant}/edit', [PmTenantDirectoryController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenant}', [PmTenantDirectoryController::class, 'update'])->middleware('property.permission:tenants.manage')->name('tenants.update');
        Route::get('/tenants/leases', [PmLeaseWebController::class, 'leases'])->name('tenants.leases');
        Route::post('/leases', [PmLeaseWebController::class, 'store'])->middleware('property.permission:leases.manage')->name('leases.store');
        Route::get('/leases/{lease}', [PmLeaseWebController::class, 'show'])->name('leases.show');
        Route::get('/leases/{lease}/edit', [PmLeaseWebController::class, 'edit'])->name('leases.edit');
        Route::put('/leases/{lease}', [PmLeaseWebController::class, 'update'])->middleware('property.permission:leases.manage')->name('leases.update');
        Route::get('/tenants/movements', [PropertyTenantsOpsWebController::class, 'movements'])->name('tenants.movements');
        Route::get('/tenants/movements/export', [PropertyTenantsOpsWebController::class, 'movementsExport'])->name('tenants.movements.export');
        Route::post('/tenants/movements', [PropertyTenantsOpsWebController::class, 'storeMovement'])->middleware('property.permission:tenants.manage')->name('tenants.movements.store');
        Route::post('/tenants/movements/{movement}/status', [PropertyTenantsOpsWebController::class, 'updateMovementStatus'])->middleware('property.permission:tenants.manage')->name('tenants.movements.status');
        Route::get('/tenants/expiry', [PmLeaseWebController::class, 'expiry'])->name('tenants.expiry');
        Route::get('/tenants/notices', [PropertyTenantsOpsWebController::class, 'notices'])->name('tenants.notices');
        Route::get('/tenants/notices/export', [PropertyTenantsOpsWebController::class, 'noticesExport'])->name('tenants.notices.export');
        Route::post('/tenants/notices', [PropertyTenantsOpsWebController::class, 'storeNotice'])->middleware('property.permission:tenants.manage')->name('tenants.notices.store');
        Route::post('/tenants/notices/{notice}/status', [PropertyTenantsOpsWebController::class, 'updateNoticeStatus'])->middleware('property.permission:tenants.manage')->name('tenants.notices.status');
        Route::view('/tenants', 'property.agent.tenants.index')->name('tenants.index');
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
        Route::get('/reports/landlord/statements', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_statements')->name('reports.landlord.statements');
        Route::get('/reports/landlord/detailed-statement', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_detailed_statement')->name('reports.landlord.detailed_statement');
        Route::get('/reports/landlord/balance-summary', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_balance_summary')->name('reports.landlord.balance_summary');
        Route::get('/reports/landlord/rental-income-commissions', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_rental_income_commissions')->name('reports.landlord.rental_income_commissions');
        Route::get('/reports/landlord/rent-collection', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_rent_collection')->name('reports.landlord.rent_collection');
        Route::get('/reports/landlord/property-statement', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'landlord_property_statement')->name('reports.landlord.property_statement');
        Route::get('/reports/expense/income-expenses-summary', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_income_expenses_summary')->name('reports.expense.income_expenses_summary');
        Route::get('/reports/expense/maintenance-expense', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_maintenance_expense')->name('reports.expense.maintenance_expense');
        Route::get('/reports/expense/utility-billing', [PropertyReportsController::class, 'reportPage'])->defaults('reportKey', 'expense_utility_billing')->name('reports.expense.utility_billing');
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
        Route::post('/properties', [PropertyPortfolioController::class, 'storeProperty'])->middleware('property.permission:properties.manage')->name('properties.store');
        Route::post('/properties/create-json', [PropertyPortfolioController::class, 'storePropertyJson'])->middleware('property.permission:properties.manage')->name('properties.store_json');
        Route::get('/properties/{property}/edit', [PropertyPortfolioController::class, 'editProperty'])->name('properties.edit');
        Route::patch('/properties/{property}', [PropertyPortfolioController::class, 'updateProperty'])->middleware('property.permission:properties.manage')->name('properties.update');
        Route::delete('/properties/{property}', [PropertyPortfolioController::class, 'destroyProperty'])->middleware('property.permission:properties.manage')->name('properties.destroy');
        Route::post('/landlords/onboard', [PropertyPortfolioController::class, 'onboardLandlord'])->name('landlords.onboard');
        Route::post('/landlords/onboard-json', [PropertyPortfolioController::class, 'onboardLandlordJson'])->name('landlords.onboard_json');
        Route::post('/properties/landlords', [PropertyPortfolioController::class, 'attachLandlord'])->name('properties.landlords.attach');
        Route::post('/properties/landlords/detach', [PropertyPortfolioController::class, 'detachLandlord'])->name('properties.landlords.detach');
        Route::post('/properties/landlords/ownership', [PropertyPortfolioController::class, 'updateLandlordOwnership'])->name('properties.landlords.ownership');
        Route::get('/landlords', [PropertyPortfolioController::class, 'landlordsIndex'])->name('landlords.index');
        Route::get('/landlords/{landlord}', [PropertyPortfolioController::class, 'landlordsShow'])->name('landlords.show');
        Route::get('/landlords/{landlord}/statement', [PropertyPortfolioController::class, 'landlordsStatement'])->name('landlords.statement');
        Route::get('/properties/units', [PropertyPortfolioController::class, 'unitList'])->name('properties.units');
        Route::get('/properties/units/export', [PropertyPortfolioController::class, 'unitListExport'])->name('properties.units.export');
        Route::post('/units', [PropertyPortfolioController::class, 'storeUnit'])->middleware('property.permission:properties.manage')->name('units.store');
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
        Route::get('/properties/{property}', [PropertyPortfolioController::class, 'showProperty'])->name('properties.show');
        Route::view('/properties', 'property.agent.properties.index')->name('properties.index');

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
        Route::view('/maintenance', 'property.agent.maintenance.index')->name('maintenance.index');

        Route::get('/vendors/directory', [PmVendorWebController::class, 'directory'])->name('vendors.directory');
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
        Route::view('/vendors', 'property.agent.vendors.index')->name('vendors.index');

        Route::get('/financials/income-expenses', [FinancialsController::class, 'incomeExpenses'])->name('financials.income_expenses');
        Route::get('/financials/cash-flow', [FinancialsController::class, 'cashFlow'])->name('financials.cash_flow');
        Route::get('/financials/owner-balances', [FinancialsController::class, 'ownerBalances'])->name('financials.owner_balances');
        Route::get('/financials/commission', [FinancialsController::class, 'commission'])->name('financials.commission');
        Route::view('/financials', 'property.agent.financials.index')->name('financials.index');

        Route::get('/accounting', [PropertyAccountingController::class, 'index'])->name('accounting.index');
        Route::get('/accounting/entries', [PropertyAccountingController::class, 'entries'])->name('accounting.entries');
        Route::get('/accounting/entries/export', [PropertyAccountingController::class, 'exportEntriesCsv'])->name('accounting.entries.export');
        Route::post('/accounting/entries', [PropertyAccountingController::class, 'storeEntry'])->middleware('property.permission:accounting.entries.manage')->name('accounting.entries.store');
        Route::post('/accounting/entries/{entry}/reverse', [PropertyAccountingController::class, 'reverseEntry'])->middleware('property.permission:accounting.entries.manage')->name('accounting.entries.reverse');
        Route::post('/accounting/settings/account-map', [PropertyAccountingController::class, 'saveAccountMap'])->name('accounting.settings.account_map.save');
        Route::get('/accounting/audit-trail', [PropertyAccountingController::class, 'auditTrail'])->name('accounting.audit_trail');
        Route::get('/accounting/audit-trail/export', [PropertyAccountingController::class, 'exportAuditTrailCsv'])->name('accounting.audit_trail.export');
        Route::get('/accounting/payroll', [PropertyAccountingController::class, 'payroll'])->name('accounting.payroll');
        Route::post('/accounting/payroll', [PropertyAccountingController::class, 'payrollStore'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.store');
        Route::post('/accounting/payroll/employee', [PropertyAccountingController::class, 'payrollEmployeeStore'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.employee.store');
        Route::get('/accounting/payroll/payslips', [PropertyAccountingController::class, 'payrollPayslips'])->name('accounting.payroll.payslips');
        Route::get('/accounting/payroll/payslips/export', [PropertyAccountingController::class, 'exportPayrollPayslipsCsv'])->name('accounting.payroll.payslips.export');
        Route::get('/accounting/payroll/payslips/{reference}', [PropertyAccountingController::class, 'payrollPayslipShow'])->name('accounting.payroll.payslips.show');
        Route::get('/accounting/payroll/settings', [PropertyAccountingController::class, 'payrollSettings'])->name('accounting.payroll.settings');
        Route::post('/accounting/payroll/settings', [PropertyAccountingController::class, 'payrollSettingsSave'])->middleware('property.permission:accounting.payroll.manage')->name('accounting.payroll.settings.save');
        Route::get('/accounting/reports/trial-balance', [PropertyAccountingController::class, 'trialBalance'])->name('accounting.reports.trial_balance');
        Route::get('/accounting/reports/trial-balance/export', [PropertyAccountingController::class, 'exportTrialBalanceCsv'])->name('accounting.reports.trial_balance.export');
        Route::get('/accounting/reports/income-statement', [PropertyAccountingController::class, 'incomeStatement'])->name('accounting.reports.income_statement');
        Route::get('/accounting/reports/income-statement/export', [PropertyAccountingController::class, 'exportIncomeStatementCsv'])->name('accounting.reports.income_statement.export');
        Route::get('/accounting/reports/cash-book', [PropertyAccountingController::class, 'cashBook'])->name('accounting.reports.cash_book');
        Route::get('/accounting/reports/cash-book/export', [PropertyAccountingController::class, 'exportCashBookCsv'])->name('accounting.reports.cash_book.export');

        Route::get('/performance/collection-rate', [PerformanceWorkspaceController::class, 'collectionRate'])->name('performance.collection_rate');
        Route::get('/performance/vacancy', [PerformanceWorkspaceController::class, 'vacancy'])->name('performance.vacancy');
        Route::get('/performance/arrears-trends', [PerformanceWorkspaceController::class, 'arrearsTrends'])->name('performance.arrears_trends');
        Route::get('/performance/maintenance-trends', [PerformanceWorkspaceController::class, 'maintenanceTrends'])->name('performance.maintenance_trends');
        Route::get('/performance/tenant-reliability', [PerformanceWorkspaceController::class, 'tenantReliability'])->name('performance.tenant_reliability');
        Route::view('/performance', 'property.agent.performance.index')->name('performance.index');

        Route::get('/notifications', [PropertyCommunicationsWebController::class, 'notifications'])->name('notifications');
        Route::get('/communications/messages', [PropertyCommunicationsWebController::class, 'messages'])->name('communications.messages');
        Route::get('/communications/messages/export', [PropertyCommunicationsWebController::class, 'messagesExport'])->name('communications.messages.export');
        Route::get('/communications/messages/{log}', [PropertyCommunicationsWebController::class, 'showMessage'])->name('communications.messages.show');
        Route::post('/communications/messages', [PropertyCommunicationsWebController::class, 'logMessage'])->middleware('property.permission:communications.manage')->name('communications.messages.store');
        Route::get('/communications/bulk', [PropertyCommunicationsWebController::class, 'bulk'])->name('communications.bulk');
        Route::get('/communications/bulk/export', [PropertyCommunicationsWebController::class, 'bulkExport'])->name('communications.bulk.export');
        Route::post('/communications/bulk', [PropertyCommunicationsWebController::class, 'logBulk'])->middleware('property.permission:communications.manage')->name('communications.bulk.store');
        Route::get('/communications/templates', [PropertyCommunicationsWebController::class, 'templates'])->name('communications.templates');
        Route::post('/communications/templates', [PropertyCommunicationsWebController::class, 'storeTemplate'])->middleware('property.permission:communications.manage')->name('communications.templates.store');
        Route::delete('/communications/templates/{template}', [PropertyCommunicationsWebController::class, 'destroyTemplate'])->middleware('property.permission:communications.manage')->name('communications.templates.destroy');
        Route::view('/communications', 'property.agent.communications.index')->name('communications.index');

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
        Route::get('/listings', [AgentPublicListingController::class, 'hub'])->name('listings.index');

        Route::get('/settings/roles', [PropertySettingsWebController::class, 'roles'])->name('settings.roles');
        Route::get('/settings/permissions', [PropertySettingsWebController::class, 'permissions'])->name('settings.permissions');
        Route::get('/settings/commission', [PropertySettingsStoreWebController::class, 'commission'])->name('settings.commission');
        Route::post('/settings/commission', [PropertySettingsStoreWebController::class, 'storeCommission'])->middleware('property.permission:settings.manage')->name('settings.commission.store');
        Route::get('/settings/payments', [PropertySettingsStoreWebController::class, 'payments'])->name('settings.payments');
        Route::post('/settings/payments', [PropertySettingsStoreWebController::class, 'storePayments'])->middleware('property.permission:settings.manage')->name('settings.payments.store');
        Route::get('/settings/branding', [PropertySettingsStoreWebController::class, 'branding'])->name('settings.branding');
        Route::post('/settings/branding', [PropertySettingsStoreWebController::class, 'storeBranding'])->middleware('property.permission:settings.manage')->name('settings.branding.store');
        Route::get('/settings/rules', [PropertySettingsStoreWebController::class, 'rules'])->name('settings.rules');
        Route::post('/settings/rules', [PropertySettingsStoreWebController::class, 'storeRules'])->middleware('property.permission:settings.manage')->name('settings.rules.store');
        Route::get('/settings/system-setup', [PropertySettingsStoreWebController::class, 'systemSetup'])->name('settings.system_setup');
        Route::get('/settings/system-setup/forms', [PropertySettingsStoreWebController::class, 'systemSetupForms'])->name('settings.system_setup.forms');
        Route::post('/settings/system-setup/forms', [PropertySettingsStoreWebController::class, 'storeSystemSetupForms'])->middleware('property.permission:settings.manage')->name('settings.system_setup.forms.store');
        Route::get('/settings/system-setup/workflows', [PropertySettingsStoreWebController::class, 'systemSetupWorkflows'])->name('settings.system_setup.workflows');
        Route::post('/settings/system-setup/workflows', [PropertySettingsStoreWebController::class, 'storeSystemSetupWorkflows'])->middleware('property.permission:settings.manage')->name('settings.system_setup.workflows.store');
        Route::get('/settings/system-setup/templates', [PropertySettingsStoreWebController::class, 'systemSetupTemplates'])->name('settings.system_setup.templates');
        Route::post('/settings/system-setup/templates', [PropertySettingsStoreWebController::class, 'storeSystemSetupTemplates'])->middleware('property.permission:settings.manage')->name('settings.system_setup.templates.store');
        Route::get('/settings/system-setup/access', [PropertySettingsStoreWebController::class, 'systemSetupAccess'])->name('settings.system_setup.access');
        Route::post('/settings/system-setup/access/roles', [PropertySettingsStoreWebController::class, 'storeSystemSetupRole'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.roles.store');
        Route::post('/settings/system-setup/access/roles/clone', [PropertySettingsStoreWebController::class, 'storeSystemSetupRoleClone'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.roles.clone');
        Route::post('/settings/system-setup/access/permissions', [PropertySettingsStoreWebController::class, 'storeSystemSetupPermission'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.permissions.store');
        Route::patch('/settings/system-setup/access/permissions/{pmPermission}', [PropertySettingsStoreWebController::class, 'updateSystemSetupPermission'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.permissions.update');
        Route::delete('/settings/system-setup/access/permissions/{pmPermission}', [PropertySettingsStoreWebController::class, 'destroySystemSetupPermission'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.permissions.destroy');
        Route::post('/settings/system-setup/access/roles/{pmRole}/permissions', [PropertySettingsStoreWebController::class, 'storeSystemSetupRolePermissions'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.roles.permissions.store');
        Route::post('/settings/system-setup/access/users/{user}/roles', [PropertySettingsStoreWebController::class, 'storeSystemSetupUserRoles'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.users.roles.store');
        Route::post('/settings/system-setup/access/users/{user}/permissions', [PropertySettingsStoreWebController::class, 'storeSystemSetupUserPermissions'])->middleware('property.permission:settings.access.manage')->name('settings.system_setup.access.users.permissions.store');
        Route::view('/settings', 'property.agent.settings.index')->name('settings.index');

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

    Route::middleware(['property.portal:landlord'])->prefix('property/landlord')->name('property.landlord.')->group(function () {
        Route::get('/portfolio', [LandlordPortalController::class, 'portfolio'])->name('portfolio');
        Route::get('/earnings', [LandlordPortalController::class, 'earnings'])->name('earnings.index');
        Route::get('/earnings/withdraw', [LandlordPortalController::class, 'withdraw'])->name('earnings.withdraw');
        Route::post('/earnings/withdraw', [LandlordPortalController::class, 'withdrawStore'])->name('earnings.withdraw.store');
        Route::get('/earnings/settings', [LandlordPortalController::class, 'payoutSettings'])->name('earnings.settings');
        Route::post('/earnings/settings', [LandlordPortalController::class, 'savePayoutSettings'])->name('earnings.settings.store');
        Route::get('/earnings/history', [LandlordPortalController::class, 'history'])->name('earnings.history');
        Route::get('/earnings/history/export', [LandlordPortalController::class, 'exportHistoryCsv'])->name('earnings.history.export');
        Route::get('/properties', [LandlordPortalController::class, 'properties'])->name('properties');
        Route::get('/properties/export', [LandlordPortalController::class, 'exportPropertiesCsv'])->name('properties.export');
        Route::get('/reports/income', [LandlordPortalController::class, 'reportIncome'])->name('reports.income');
        Route::get('/reports/income/export', [LandlordPortalController::class, 'exportIncomeReportCsv'])->name('reports.income.export');
        Route::get('/reports/expenses', [LandlordPortalController::class, 'reportExpenses'])->name('reports.expenses');
        Route::get('/reports/expenses/export', [LandlordPortalController::class, 'exportExpensesReportCsv'])->name('reports.expenses.export');
        Route::get('/reports/cash-flow', [LandlordPortalController::class, 'reportCashFlow'])->name('reports.cash_flow');
        Route::get('/reports/statement', [LandlordPortalController::class, 'statement'])->name('reports.statement');
        Route::get('/reports/statement/export', [LandlordPortalController::class, 'exportStatementCsv'])->name('reports.statement.export');
        Route::view('/reports', 'property.landlord.reports.index')->name('reports.index');
        Route::get('/maintenance', [LandlordPortalController::class, 'maintenance'])->name('maintenance');
        Route::post('/maintenance/threshold', [LandlordPortalController::class, 'saveMaintenanceThreshold'])->name('maintenance.threshold.store');
        Route::post('/maintenance/jobs/{job}/approval', [LandlordPortalController::class, 'approveMaintenanceJob'])->name('maintenance.jobs.approval');
        Route::get('/notifications', [LandlordPortalController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/preferences', [LandlordPortalController::class, 'saveNotificationPreferences'])->name('notifications.preferences.store');
        Route::get('/documents', [LandlordPortalController::class, 'documents'])->name('documents');
        Route::get('/audit-trail', [LandlordPortalController::class, 'auditTrail'])->name('audit_trail');
        Route::get('/audit-trail/export', [LandlordPortalController::class, 'exportAuditTrailCsv'])->name('audit_trail.export');
        Route::view('/opportunities', 'property.landlord.opportunities')->name('opportunities');

        Route::get('/quick-action', function () {
            return redirect()
                ->route('property.landlord.portfolio')
                ->with('success', 'That address is for form submissions only. Use an action button on the page you came from.');
        });
        Route::post('/quick-action', [PropertyPortalQuickActionController::class, 'storeLandlord'])->name('quick_action.store');
    });

    Route::middleware(['property.portal:tenant'])->prefix('property/tenant')->name('property.tenant.')->group(function () {
        Route::get('/home', [TenantPortalController::class, 'home'])->name('home');
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

        Route::get('/workspace/forms/{form}', [TenantWorkspaceFormController::class, 'show'])
            ->where('form', '[a-z0-9\-]+')
            ->name('workspace.form.show');
        Route::post('/workspace/forms/{form}', [TenantWorkspaceFormController::class, 'store'])
            ->where('form', '[a-z0-9\-]+')
            ->name('workspace.form.store');
        Route::get('/lease', [TenantPortalController::class, 'lease'])->name('lease');
        Route::view('/maintenance', 'property.tenant.maintenance.index')->name('maintenance.index');
        Route::get('/maintenance/report', [TenantPortalController::class, 'maintenanceReport'])->name('maintenance.report');
        Route::post('/maintenance/report', [TenantPortalController::class, 'maintenanceReportSubmit'])->name('maintenance.report.store');
        Route::get('/requests', [TenantPortalController::class, 'requestsPage'])->name('requests');
        Route::post('/requests', [TenantPortalController::class, 'storePortalRequest'])->name('requests.store');
        Route::get('/notifications', [TenantPortalController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [TenantPortalController::class, 'notificationsReadAll'])->name('notifications.read_all');
        Route::post('/notifications/{log}/read', [TenantPortalController::class, 'notificationsReadOne'])->whereNumber('log')->name('notifications.read_one');
        Route::get('/explore', [TenantPortalController::class, 'explore'])->name('explore');

        Route::get('/quick-action', function () {
            return redirect()
                ->route('property.tenant.home')
                ->with('success', 'That address is for form submissions only. Use an action button on the page you came from.');
        });
        Route::post('/quick-action', [PropertyPortalQuickActionController::class, 'storeTenant'])->name('quick_action.store');
    });
});
