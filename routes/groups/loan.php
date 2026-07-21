<?php

use App\Http\Controllers\Loan\LoanAccountController;
use App\Http\Controllers\Loan\LoanAccountingBooksController;
use App\Http\Controllers\Loan\LoanAccountingController;
use App\Http\Controllers\Loan\LoanAssetFinancingController;
use App\Http\Controllers\Loan\LoanBookApplicationsController;
use App\Http\Controllers\Loan\LoanBookLoansController;
use App\Http\Controllers\Loan\LoanBookOperationsController;
use App\Http\Controllers\Loan\LoanBulkSmsController;
use App\Http\Controllers\Loan\LoanCommunicationsWebController;
use App\Http\Controllers\Loan\LoanBusinessAnalyticsController;
use App\Http\Controllers\Loan\LoanClientLeadPipelineController;
use App\Http\Controllers\Loan\LoanClientsController;
use App\Http\Controllers\Loan\LoanClientWalletController;
use App\Http\Controllers\Loan\LoanDashboardController;
use App\Http\Controllers\Loan\LoanEmployeesController;
use App\Http\Controllers\Loan\LoanFinancialController;
use App\Http\Controllers\Loan\LoanFormSetupController;
use App\Http\Controllers\Loan\LoanHrController;
use App\Http\Controllers\Loan\LoanNotificationController;
use App\Http\Controllers\Loan\LoanOrganizationController;
use App\Http\Controllers\Loan\LoanPaymentsController;
use App\Http\Controllers\Loan\LoanSystemHelpController;
use Illuminate\Support\Facades\Route;

Route::get('/loan/dashboard', [LoanDashboardController::class, 'index'])->name('loan.dashboard');
Route::post('/loan/dashboard/sms-topup', [LoanDashboardController::class, 'smsWalletTopupFromDashboard'])->name('loan.dashboard.sms_topup');
Route::get('/loan/dashboard/sms-topup/status', [LoanDashboardController::class, 'smsTopupStatusJson'])->name('loan.dashboard.sms_topup_status');
Route::get('/loan/dashboard/performance-targets', [LoanDashboardController::class, 'performanceTargets'])->name('loan.dashboard.performance_targets');
Route::post('/loan/dashboard/performance-targets', [LoanDashboardController::class, 'performanceTargetsUpdate'])->name('loan.dashboard.performance_targets.update');
Route::prefix('loan/notifications')->name('loan.notifications.')->group(function () {
    Route::get('/', [LoanNotificationController::class, 'index'])->name('index');
    Route::post('/read-all', [LoanNotificationController::class, 'readAll'])->name('read_all');
    Route::post('/{notification}/read', [LoanNotificationController::class, 'readOne'])->name('read_one');
});

Route::prefix('loan/financial')->middleware('loan.role:accountant,admin,manager')->name('loan.financial.')->group(function () {
    Route::get('/mpesa-platform', [LoanFinancialController::class, 'mpesaPlatform'])->name('mpesa_platform');
    Route::post('/mpesa-platform/transactions', [LoanFinancialController::class, 'mpesaPlatformTransactionStore'])->name('mpesa_platform.transactions.store');
    Route::delete('/mpesa-platform/transactions/{mpesa_platform_transaction}', [LoanFinancialController::class, 'mpesaPlatformTransactionDestroy'])->name('mpesa_platform.transactions.destroy');

    Route::get('/mpesa-payouts/create', [LoanFinancialController::class, 'mpesaPayoutsCreate'])->name('mpesa_payouts.create');
    Route::post('/mpesa-payouts', [LoanFinancialController::class, 'mpesaPayoutsStore'])->name('mpesa_payouts.store');
    Route::get('/mpesa-payouts/{mpesa_payout_batch}/edit', [LoanFinancialController::class, 'mpesaPayoutsEdit'])->name('mpesa_payouts.edit');
    Route::patch('/mpesa-payouts/{mpesa_payout_batch}', [LoanFinancialController::class, 'mpesaPayoutsUpdate'])->name('mpesa_payouts.update');
    Route::delete('/mpesa-payouts/{mpesa_payout_batch}', [LoanFinancialController::class, 'mpesaPayoutsDestroy'])->name('mpesa_payouts.destroy');
    Route::get('/mpesa-payouts', [LoanFinancialController::class, 'mpesaPayouts'])->name('mpesa_payouts');

    Route::get('/account-balances/create', [LoanFinancialController::class, 'financialAccountsCreate'])->name('accounts.create');
    Route::post('/account-balances', [LoanFinancialController::class, 'financialAccountsStore'])->name('accounts.store');
    Route::get('/account-balances/{financial_account}/edit', [LoanFinancialController::class, 'financialAccountsEdit'])->name('accounts.edit');
    Route::patch('/account-balances/{financial_account}', [LoanFinancialController::class, 'financialAccountsUpdate'])->name('accounts.update');
    Route::delete('/account-balances/{financial_account}', [LoanFinancialController::class, 'financialAccountsDestroy'])->name('accounts.destroy');
    Route::get('/account-balances', [LoanFinancialController::class, 'accountBalances'])->name('account_balances');
    Route::get('/control-accounts', [LoanFinancialController::class, 'controlAccounts'])->name('control_accounts');

    Route::get('/wallet-refunds', [LoanFinancialController::class, 'walletRefundsIndex'])
        ->middleware('loan.permission:wallets.refund_approve')
        ->name('wallet_refunds.index');
    Route::post('/wallet-refunds/{client_wallet_refund_request}/approve', [LoanFinancialController::class, 'walletRefundApprove'])
        ->middleware('loan.permission:wallets.refund_approve')
        ->name('wallet_refunds.approve');
    Route::post('/wallet-refunds/{client_wallet_refund_request}/reject', [LoanFinancialController::class, 'walletRefundReject'])
        ->middleware('loan.permission:wallets.refund_approve')
        ->name('wallet_refunds.reject');

    Route::post('/teller-sessions', [LoanFinancialController::class, 'tellerSessionStore'])->name('teller_sessions.store');
    Route::get('/teller-sessions/{teller_session}', [LoanFinancialController::class, 'tellerSessionShow'])->name('teller_sessions.show');
    Route::post('/teller-sessions/{teller_session}/movements', [LoanFinancialController::class, 'tellerMovementStore'])->name('teller_sessions.movements.store');
    Route::post('/teller-sessions/{teller_session}/close', [LoanFinancialController::class, 'tellerSessionClose'])->name('teller_sessions.close');
    Route::get('/teller-operations', [LoanFinancialController::class, 'tellerOperations'])->name('teller_operations');

    Route::get('/investment-packages/create', [LoanFinancialController::class, 'investmentPackagesCreate'])->name('packages.create');
    Route::post('/investment-packages', [LoanFinancialController::class, 'investmentPackagesStore'])->name('packages.store');
    Route::get('/investment-packages/{investment_package}/edit', [LoanFinancialController::class, 'investmentPackagesEdit'])->name('packages.edit');
    Route::patch('/investment-packages/{investment_package}', [LoanFinancialController::class, 'investmentPackagesUpdate'])->name('packages.update');
    Route::delete('/investment-packages/{investment_package}', [LoanFinancialController::class, 'investmentPackagesDestroy'])->name('packages.destroy');
    Route::get('/investment-packages', [LoanFinancialController::class, 'investmentPackages'])->name('investment_packages');

    Route::get('/investors/create', [LoanFinancialController::class, 'investorsCreate'])->name('investors.create');
    Route::get('/investors/{investor}/edit', [LoanFinancialController::class, 'investorsEdit'])->name('investors.edit');
    Route::post('/investors', [LoanFinancialController::class, 'investorsStore'])->name('investors.store');
    Route::patch('/investors/{investor}', [LoanFinancialController::class, 'investorsUpdate'])->name('investors.update');
    Route::delete('/investors/{investor}', [LoanFinancialController::class, 'investorsDestroy'])->name('investors.destroy');
    Route::get('/investors', [LoanFinancialController::class, 'investorsList'])->name('investors_list');

    Route::get('/investors-reports/export/statement', [LoanFinancialController::class, 'investorsReportsStatementCsv'])->name('investors_reports.export.statement');
    Route::get('/investors-reports/export/maturity', [LoanFinancialController::class, 'investorsReportsMaturityCsv'])->name('investors_reports.export.maturity');
    Route::get('/investors-reports', [LoanFinancialController::class, 'investorsReports'])->name('investors_reports');
});

Route::prefix('loan/account')->name('loan.account.')->group(function () {
    Route::get('/', [LoanAccountController::class, 'show'])->name('show');
    Route::get('/salary-advance', [LoanAccountController::class, 'salaryAdvance'])->name('salary_advance');
    Route::get('/approval-requests', [LoanAccountController::class, 'approvalRequests'])->name('approval_requests');
});

Route::prefix('loan/clients')->name('loan.clients.')->group(function () {
    Route::get('/', [LoanClientsController::class, 'index'])->name('index');
    Route::get('/create', [LoanClientsController::class, 'create'])->name('create');
    Route::post('/branches', [LoanClientsController::class, 'branchStore'])->name('branches.store');
    Route::post('/', [LoanClientsController::class, 'store'])->name('store');

    Route::get('/leads', [LoanClientsController::class, 'leads'])->name('leads');
    Route::get('/leads/intelligence', [LoanClientsController::class, 'leadsIntelligence'])->name('leads.intelligence');
    Route::get('/leads/create', [LoanClientsController::class, 'leadsCreate'])->name('leads.create');
    Route::post('/leads', [LoanClientsController::class, 'leadsStore'])->name('leads.store');
    Route::post('/leads/{loan_client}/convert', [LoanClientsController::class, 'leadsConvert'])->name('leads.convert');
    Route::get('/leads/{loan_client}', [LoanClientsController::class, 'leadShow'])->name('leads.show');
    Route::post('/leads/{loan_client}/lead-pipeline/stage', [LoanClientLeadPipelineController::class, 'updateStage'])->name('leads.pipeline.stage');
    Route::post('/leads/{loan_client}/lead-pipeline/activity', [LoanClientLeadPipelineController::class, 'storeActivity'])->name('leads.pipeline.activity');
    Route::post('/leads/{loan_client}/lead-pipeline/loss', [LoanClientLeadPipelineController::class, 'storeLoss'])->name('leads.pipeline.loss');

    Route::get('/transfer', [LoanClientsController::class, 'transfer'])->name('transfer');
    Route::post('/transfer', [LoanClientsController::class, 'transferStore'])->name('transfer.store');

    Route::get('/default-groups/create', [LoanClientsController::class, 'defaultGroupsCreate'])->name('default_groups.create');
    Route::post('/default-groups', [LoanClientsController::class, 'defaultGroupsStore'])->name('default_groups.store');
    Route::get('/default-groups', [LoanClientsController::class, 'defaultGroups'])->name('default_groups');
    Route::get('/default-groups/{default_client_group}', [LoanClientsController::class, 'defaultGroupsShow'])->name('default_groups.show');
    Route::get('/default-groups/{default_client_group}/edit', [LoanClientsController::class, 'defaultGroupsEdit'])->name('default_groups.edit');
    Route::patch('/default-groups/{default_client_group}', [LoanClientsController::class, 'defaultGroupsUpdate'])->name('default_groups.update');
    Route::delete('/default-groups/{default_client_group}', [LoanClientsController::class, 'defaultGroupsDestroy'])->name('default_groups.destroy');
    Route::post('/default-groups/{default_client_group}/members', [LoanClientsController::class, 'defaultGroupsMemberStore'])->name('default_groups.members.store');
    Route::delete('/default-groups/{default_client_group}/members/{loan_client}', [LoanClientsController::class, 'defaultGroupsMemberDestroy'])->name('default_groups.members.destroy');

    Route::get('/interactions', [LoanClientsController::class, 'interactions'])->name('interactions');
    Route::get('/interactions/create', [LoanClientsController::class, 'interactionsCreate'])->name('interactions.create');
    Route::post('/interactions', [LoanClientsController::class, 'interactionsStore'])->name('interactions.store');
    Route::get('/interactions/{client_interaction}', [LoanClientsController::class, 'interactionsShow'])->name('interactions.show');

    Route::get('/{loan_client}/interactions/create', [LoanClientsController::class, 'interactionCreateForClient'])->name('interactions.for_client.create');
    Route::post('/{loan_client}/interactions', [LoanClientsController::class, 'interactionStoreForClient'])->name('interactions.for_client.store');
    Route::patch('/{loan_client}/loans/{loan_book_loan}/collection-agent', [LoanClientsController::class, 'assignLoanCollectionAgent'])->name('loans.collection_agent.assign');

    Route::get('/{loan_client}/wallet/statement.csv', [LoanClientWalletController::class, 'statementExport'])
        ->middleware('loan.permission:wallets.view')
        ->name('wallet.statement');
    Route::get('/{loan_client}/wallet/pay-loan', [LoanClientWalletController::class, 'payLoanCreate'])
        ->middleware('loan.permission:wallets.pay_loan')
        ->name('wallet.pay_loan.create');
    Route::post('/{loan_client}/wallet/pay-loan', [LoanClientWalletController::class, 'payLoanStore'])
        ->middleware('loan.permission:wallets.pay_loan')
        ->name('wallet.pay_loan.store');
    Route::get('/{loan_client}/wallet/refund-request', [LoanClientWalletController::class, 'refundRequestCreate'])
        ->middleware('loan.permission:wallets.refund_request')
        ->name('wallet.refund_request.create');
    Route::post('/{loan_client}/wallet/refund-request', [LoanClientWalletController::class, 'refundRequestStore'])
        ->middleware('loan.permission:wallets.refund_request')
        ->name('wallet.refund_request.store');
    Route::post('/{loan_client}/wallet/freeze', [LoanClientWalletController::class, 'freeze'])
        ->middleware('loan.permission:wallets.freeze')
        ->name('wallet.freeze');
    Route::post('/{loan_client}/wallet/unfreeze', [LoanClientWalletController::class, 'unfreeze'])
        ->middleware('loan.permission:wallets.freeze')
        ->name('wallet.unfreeze');
    Route::post('/{loan_client}/wallet/adjust', [LoanClientWalletController::class, 'adjust'])
        ->middleware('loan.permission:wallets.adjust')
        ->name('wallet.adjust');

    Route::get('/{loan_client}', [LoanClientsController::class, 'show'])->name('show');
    Route::get('/{loan_client}/edit', [LoanClientsController::class, 'edit'])->name('edit');
    Route::patch('/{loan_client}', [LoanClientsController::class, 'update'])->name('update');
    Route::delete('/{loan_client}', [LoanClientsController::class, 'destroy'])->name('destroy');
});

Route::prefix('loan/employees')->middleware('loan.permission:employees.view')->name('loan.employees.')->group(function () {
    Route::get('/', [LoanEmployeesController::class, 'index'])->name('index');
    Route::get('/create', [LoanEmployeesController::class, 'create'])->middleware('loan.permission:employees.create')->name('create');
    Route::post('/', [LoanEmployeesController::class, 'store'])->middleware('loan.permission:employees.create')->name('store');
    Route::post('/departments', [LoanEmployeesController::class, 'departmentsStore'])->middleware('loan.permission:employees.configure')->name('departments.store');
    Route::post('/branches', [LoanEmployeesController::class, 'branchesStore'])->middleware('loan.permission:employees.configure')->name('branches.store');
    Route::post('/bulk-delete', [LoanEmployeesController::class, 'employeesBulkDelete'])->middleware('loan.permission:employees.delete')->name('bulk_delete');

    Route::get('/leaves/create', [LoanEmployeesController::class, 'leavesCreate'])->middleware('loan.permission:employees.create')->name('leaves.create');
    Route::post('/leaves', [LoanEmployeesController::class, 'leavesStore'])->middleware('loan.permission:employees.create')->name('leaves.store');
    Route::patch('/leaves/{staff_leave}/status', [LoanEmployeesController::class, 'leavesUpdateStatus'])->middleware('loan.permission:employees.update')->name('leaves.status');
    Route::post('/leaves/bulk-status', [LoanEmployeesController::class, 'leavesBulkStatus'])->middleware('loan.permission:employees.update')->name('leaves.bulk_status');
    Route::get('/leaves', [LoanEmployeesController::class, 'leaves'])->name('leaves');

    Route::get('/groups/create', [LoanEmployeesController::class, 'groupsCreate'])->middleware('loan.permission:employees.create')->name('groups.create');
    Route::post('/groups', [LoanEmployeesController::class, 'groupsStore'])->middleware('loan.permission:employees.create')->name('groups.store');
    Route::get('/groups', [LoanEmployeesController::class, 'groups'])->name('groups');
    Route::get('/groups/{staff_group}', [LoanEmployeesController::class, 'groupsShow'])->name('groups.show');
    Route::patch('/groups/{staff_group}', [LoanEmployeesController::class, 'groupsUpdate'])->middleware('loan.permission:employees.update')->name('groups.update');
    Route::post('/groups/{staff_group}/members', [LoanEmployeesController::class, 'groupsMemberStore'])->middleware('loan.permission:employees.update')->name('groups.members.store');
    Route::delete('/groups/{staff_group}/members/{employee}', [LoanEmployeesController::class, 'groupsMemberDestroy'])->middleware('loan.permission:employees.delete')->name('groups.members.destroy');
    Route::delete('/groups/{staff_group}', [LoanEmployeesController::class, 'groupsDestroy'])->middleware('loan.permission:employees.delete')->name('groups.destroy');

    Route::get('/portfolios/create', [LoanEmployeesController::class, 'portfoliosCreate'])->middleware('loan.permission:employees.create')->name('portfolios.create');
    Route::post('/portfolios', [LoanEmployeesController::class, 'portfoliosStore'])->middleware('loan.permission:employees.create')->name('portfolios.store');
    Route::post('/portfolios/bulk-delete', [LoanEmployeesController::class, 'portfoliosBulkDelete'])->middleware('loan.permission:employees.delete')->name('portfolios.bulk_delete');
    Route::get('/portfolios', [LoanEmployeesController::class, 'portfolios'])->name('portfolios');
    Route::get('/portfolios/{staff_portfolio}/edit', [LoanEmployeesController::class, 'portfoliosEdit'])->middleware('loan.permission:employees.update')->name('portfolios.edit');
    Route::patch('/portfolios/{staff_portfolio}', [LoanEmployeesController::class, 'portfoliosUpdate'])->middleware('loan.permission:employees.update')->name('portfolios.update');
    Route::delete('/portfolios/{staff_portfolio}', [LoanEmployeesController::class, 'portfoliosDestroy'])->middleware('loan.permission:employees.delete')->name('portfolios.destroy');

    Route::get('/loan-applications/create', [LoanEmployeesController::class, 'loanApplicationsCreate'])->middleware('loan.permission:employees.create')->name('loan_applications.create');
    Route::post('/loan-applications', [LoanEmployeesController::class, 'loanApplicationsStore'])->middleware('loan.permission:employees.create')->name('loan_applications.store');
    Route::post('/loan-applications/bulk-status', [LoanEmployeesController::class, 'loanApplicationsBulkStatus'])->middleware('loan.permission:employees.update')->name('loan_applications.bulk_status');
    Route::get('/loan-applications', [LoanEmployeesController::class, 'loanApplications'])->name('loan_applications');
    Route::patch('/loan-applications/{staff_loan_application}', [LoanEmployeesController::class, 'loanApplicationsUpdate'])->middleware('loan.permission:employees.update')->name('loan_applications.update');

    Route::get('/staff-loans/create', [LoanEmployeesController::class, 'staffLoansCreate'])->middleware('loan.permission:employees.create')->name('staff_loans.create');
    Route::post('/staff-loans', [LoanEmployeesController::class, 'staffLoansStore'])->middleware('loan.permission:employees.create')->name('staff_loans.store');
    Route::post('/staff-loans/bulk', [LoanEmployeesController::class, 'staffLoansBulk'])->middleware('loan.permission:employees.update')->name('staff_loans.bulk');
    Route::get('/staff-loans', [LoanEmployeesController::class, 'staffLoans'])->name('staff_loans');
    Route::get('/staff-loans/{staff_loan}/edit', [LoanEmployeesController::class, 'staffLoansEdit'])->middleware('loan.permission:employees.update')->name('staff_loans.edit');
    Route::patch('/staff-loans/{staff_loan}', [LoanEmployeesController::class, 'staffLoansUpdate'])->middleware('loan.permission:employees.update')->name('staff_loans.update');
    Route::delete('/staff-loans/{staff_loan}', [LoanEmployeesController::class, 'staffLoansDestroy'])->middleware('loan.permission:employees.delete')->name('staff_loans.destroy');

    Route::post('/workplan/items', [LoanEmployeesController::class, 'workplanItemStore'])->middleware('loan.permission:employees.create')->name('workplan.items.store');
    Route::post('/workplan/items/{workplan_item}/toggle', [LoanEmployeesController::class, 'workplanItemToggle'])->middleware('loan.permission:employees.update')->name('workplan.items.toggle');
    Route::delete('/workplan/items/{workplan_item}', [LoanEmployeesController::class, 'workplanItemDestroy'])->middleware('loan.permission:employees.delete')->name('workplan.items.destroy');
    Route::get('/workplan', [LoanEmployeesController::class, 'workplan'])->name('workplan');

    Route::get('/{employee}', [LoanEmployeesController::class, 'show'])->name('show');
    Route::post('/{employee}/resend-login', [LoanEmployeesController::class, 'resendLoginCredentials'])->middleware('loan.permission:employees.update')->name('resend_login');
    Route::get('/{employee}/edit', [LoanEmployeesController::class, 'edit'])->middleware('loan.permission:employees.update')->name('edit');
    Route::patch('/{employee}', [LoanEmployeesController::class, 'update'])->middleware('loan.permission:employees.update')->name('update');
    Route::delete('/{employee}', [LoanEmployeesController::class, 'destroy'])->middleware('loan.permission:employees.delete')->name('destroy');
});

Route::prefix('loan/hr')->middleware('loan.permission:employees.view')->name('loan.hr.')->group(function () {
    Route::get('/', [LoanHrController::class, 'dashboard'])->name('dashboard');
    Route::get('/{section}', [LoanHrController::class, 'section'])->name('section');
    Route::post('/documents', [LoanHrController::class, 'storeDocument'])->middleware('loan.permission:employees.update')->name('documents.store');
    Route::delete('/documents/{employeeDocument}', [LoanHrController::class, 'destroyDocument'])->middleware('loan.permission:employees.update')->name('documents.destroy');
    Route::post('/training', [LoanHrController::class, 'storeTraining'])->middleware('loan.permission:employees.update')->name('training.store');
    Route::delete('/training/{staffTrainingRecord}', [LoanHrController::class, 'destroyTraining'])->middleware('loan.permission:employees.update')->name('training.destroy');
});

Route::prefix('loan/regions')->name('loan.regions.')->group(function () {
    Route::get('/create', [LoanOrganizationController::class, 'regionsCreate'])->name('create');
    Route::post('/', [LoanOrganizationController::class, 'regionsStore'])->name('store');
    Route::get('/', [LoanOrganizationController::class, 'regionsIndex'])->name('index');
    Route::get('/{loan_region}/edit', [LoanOrganizationController::class, 'regionsEdit'])->name('edit');
    Route::patch('/{loan_region}', [LoanOrganizationController::class, 'regionsUpdate'])->name('update');
    Route::delete('/{loan_region}', [LoanOrganizationController::class, 'regionsDestroy'])->name('destroy');
});

Route::prefix('loan/branches')->middleware('loan.permission:branches.view')->name('loan.branches.')->group(function () {
    Route::get('/loan-summary', [LoanOrganizationController::class, 'branchLoanSummary'])->name('loan_summary');
    Route::get('/changes', [LoanOrganizationController::class, 'branchChangesIndex'])->name('changes.index');
    Route::post('/changes/{change}/approve', [LoanOrganizationController::class, 'branchChangesApprove'])->middleware('loan.permission:branches.approve')->name('changes.approve');
    Route::post('/changes/{change}/reject', [LoanOrganizationController::class, 'branchChangesReject'])->middleware('loan.permission:branches.approve')->name('changes.reject');
    Route::get('/create', [LoanOrganizationController::class, 'branchesCreate'])->middleware('loan.permission:branches.create')->name('create');
    Route::post('/', [LoanOrganizationController::class, 'branchesStore'])->middleware('loan.permission:branches.create')->name('store');
    Route::get('/', [LoanOrganizationController::class, 'branchesIndex'])->name('index');
    Route::get('/{loan_branch}/edit', [LoanOrganizationController::class, 'branchesEdit'])->middleware('loan.permission:branches.update')->name('edit');
    Route::patch('/{loan_branch}', [LoanOrganizationController::class, 'branchesUpdate'])->middleware('loan.permission:branches.update')->name('update');
    Route::delete('/{loan_branch}', [LoanOrganizationController::class, 'branchesDestroy'])->middleware('loan.permission:branches.delete')->name('destroy');
});

Route::prefix('loan/analytics')->middleware('loan.permission:analytics.view')->name('loan.analytics.')->group(function () {
    Route::get('/loan-sizes/create', [LoanBusinessAnalyticsController::class, 'loanSizesCreate'])->middleware('loan.permission:analytics.create')->name('loan_sizes.create');
    Route::post('/loan-sizes', [LoanBusinessAnalyticsController::class, 'loanSizesStore'])->middleware('loan.permission:analytics.create')->name('loan_sizes.store');
    Route::get('/loan-sizes', [LoanBusinessAnalyticsController::class, 'loanSizesIndex'])->name('loan_sizes');
    Route::get('/loan-sizes/{analytics_loan_size}/edit', [LoanBusinessAnalyticsController::class, 'loanSizesEdit'])->middleware('loan.permission:analytics.update')->name('loan_sizes.edit');
    Route::patch('/loan-sizes/{analytics_loan_size}', [LoanBusinessAnalyticsController::class, 'loanSizesUpdate'])->middleware('loan.permission:analytics.update')->name('loan_sizes.update');
    Route::delete('/loan-sizes/{analytics_loan_size}', [LoanBusinessAnalyticsController::class, 'loanSizesDestroy'])->middleware('loan.permission:analytics.delete')->name('loan_sizes.destroy');

    Route::get('/targets/create', [LoanBusinessAnalyticsController::class, 'targetsCreate'])->middleware('loan.permission:analytics.create')->name('targets.create');
    Route::post('/targets', [LoanBusinessAnalyticsController::class, 'targetsStore'])->middleware('loan.permission:analytics.create')->name('targets.store');
    Route::get('/targets', [LoanBusinessAnalyticsController::class, 'targetsIndex'])->name('targets');
    Route::get('/targets/{analytics_period_target}/edit', [LoanBusinessAnalyticsController::class, 'targetsEdit'])->middleware('loan.permission:analytics.update')->name('targets.edit');
    Route::patch('/targets/{analytics_period_target}', [LoanBusinessAnalyticsController::class, 'targetsUpdate'])->middleware('loan.permission:analytics.update')->name('targets.update');
    Route::delete('/targets/{analytics_period_target}', [LoanBusinessAnalyticsController::class, 'targetsDestroy'])->middleware('loan.permission:analytics.delete')->name('targets.destroy');

    Route::get('/performance/create', [LoanBusinessAnalyticsController::class, 'performanceCreate'])->middleware('loan.permission:analytics.create')->name('performance.create');
    Route::post('/performance', [LoanBusinessAnalyticsController::class, 'performanceStore'])->middleware('loan.permission:analytics.create')->name('performance.store');
    Route::get('/performance', [LoanBusinessAnalyticsController::class, 'performanceIndex'])->name('performance');
    Route::get('/performance/{analytics_performance_record}/edit', [LoanBusinessAnalyticsController::class, 'performanceEdit'])->middleware('loan.permission:analytics.update')->name('performance.edit');
    Route::patch('/performance/{analytics_performance_record}', [LoanBusinessAnalyticsController::class, 'performanceUpdate'])->middleware('loan.permission:analytics.update')->name('performance.update');
    Route::delete('/performance/{analytics_performance_record}', [LoanBusinessAnalyticsController::class, 'performanceDestroy'])->middleware('loan.permission:analytics.delete')->name('performance.destroy');
});

Route::prefix('loan/accounting')->middleware('loan.role:accountant,admin,manager')->name('loan.accounting.')->group(function () {
    Route::get('/expense-summary', [LoanAccountingController::class, 'expenseSummary'])->name('expense_summary');
    Route::get('/cashflow', [LoanAccountingController::class, 'cashflow'])->name('cashflow');

    Route::get('/books', [LoanAccountingController::class, 'books'])->name('books');
    Route::get('/books/chart-rules', [LoanAccountingBooksController::class, 'chartRules'])->name('books.chart_rules');
    Route::get('/books/chart-rules/template/download', [LoanAccountingBooksController::class, 'downloadChartTemplate'])->name('books.chart_rules.template.download');
    Route::post('/books/chart-rules/import', [LoanAccountingBooksController::class, 'importChartTemplate'])->name('books.chart_rules.import');
    Route::post('/books/chart-rules/{accounting_chart_account}/approve', [LoanAccountingBooksController::class, 'approvePendingAccount'])->name('books.chart_rules.approve');
    Route::post('/books/chart-rules/{accounting_chart_account}/reject', [LoanAccountingBooksController::class, 'rejectPendingAccount'])->name('books.chart_rules.reject');

    Route::get('/reports', [LoanAccountingBooksController::class, 'reportsHub'])->name('reports.hub');
    Route::get('/reports/trial-balance', [LoanAccountingBooksController::class, 'trialBalance'])->name('reports.trial_balance');
    Route::get('/reports/income-statement', [LoanAccountingBooksController::class, 'incomeStatement'])->name('reports.income_statement');
    Route::get('/reports/balance-sheet', [LoanAccountingBooksController::class, 'balanceSheet'])->name('reports.balance_sheet');

    Route::get('/company-expenses/create', [LoanAccountingBooksController::class, 'companyExpensesCreate'])->name('company_expenses.create');
    Route::post('/company-expenses', [LoanAccountingBooksController::class, 'companyExpensesStore'])->name('company_expenses.store');
    Route::get('/company-expenses', [LoanAccountingBooksController::class, 'companyExpensesIndex'])->name('company_expenses.index');
    Route::get('/company-expenses/{accounting_company_expense}/edit', [LoanAccountingBooksController::class, 'companyExpensesEdit'])->name('company_expenses.edit');
    Route::patch('/company-expenses/{accounting_company_expense}', [LoanAccountingBooksController::class, 'companyExpensesUpdate'])->name('company_expenses.update');
    Route::delete('/company-expenses/{accounting_company_expense}', [LoanAccountingBooksController::class, 'companyExpensesDestroy'])->name('company_expenses.destroy');

    Route::get('/company-assets/create', [LoanAccountingBooksController::class, 'assetsCreate'])->name('company_assets.create');
    Route::post('/company-assets', [LoanAccountingBooksController::class, 'assetsStore'])->name('company_assets.store');
    Route::get('/company-assets', [LoanAccountingBooksController::class, 'assetsIndex'])->name('company_assets.index');
    Route::get('/company-assets/{accounting_company_asset}/edit', [LoanAccountingBooksController::class, 'assetsEdit'])->name('company_assets.edit');
    Route::patch('/company-assets/{accounting_company_asset}', [LoanAccountingBooksController::class, 'assetsUpdate'])->name('company_assets.update');
    Route::delete('/company-assets/{accounting_company_asset}', [LoanAccountingBooksController::class, 'assetsDestroy'])->name('company_assets.destroy');

    Route::get('/payroll', [LoanAccountingBooksController::class, 'payrollHub'])->name('payroll.hub');
    Route::get('/payroll/payslips', [LoanAccountingBooksController::class, 'payrollPayslipsIndex'])->name('payroll.payslips.index');
    Route::get('/payroll/settings/statutory-deductions', [LoanAccountingBooksController::class, 'payrollStatutorySettings'])->name('payroll.settings.statutory');
    Route::get('/payroll/settings/other-deductions', [LoanAccountingBooksController::class, 'payrollOtherDeductionsSettings'])->name('payroll.settings.other_deductions');
    Route::get('/payroll/settings/bonuses-allowances', [LoanAccountingBooksController::class, 'payrollBonusesAllowancesSettings'])->name('payroll.settings.bonuses');

    Route::get('/payroll/periods/create', [LoanAccountingBooksController::class, 'payrollCreate'])->name('payroll.create');
    Route::post('/payroll/periods', [LoanAccountingBooksController::class, 'payrollStore'])->name('payroll.store');
    Route::get('/payroll/periods', [LoanAccountingBooksController::class, 'payrollIndex'])->name('payroll.index');
    Route::get('/payroll/periods/{accounting_payroll_period}', [LoanAccountingBooksController::class, 'payrollShow'])->name('payroll.show');
    Route::get('/payroll/periods/{accounting_payroll_period}/edit', [LoanAccountingBooksController::class, 'payrollEdit'])->name('payroll.edit');
    Route::patch('/payroll/periods/{accounting_payroll_period}', [LoanAccountingBooksController::class, 'payrollUpdate'])->name('payroll.update');
    Route::delete('/payroll/periods/{accounting_payroll_period}', [LoanAccountingBooksController::class, 'payrollDestroy'])->name('payroll.destroy');
    Route::post('/payroll/periods/{accounting_payroll_period}/lines', [LoanAccountingBooksController::class, 'payrollLineStore'])->name('payroll.lines.store');
    Route::patch('/payroll/periods/{accounting_payroll_period}/lines/{accounting_payroll_line}', [LoanAccountingBooksController::class, 'payrollLineUpdate'])->name('payroll.lines.update');
    Route::delete('/payroll/periods/{accounting_payroll_period}/lines/{accounting_payroll_line}', [LoanAccountingBooksController::class, 'payrollLineDestroy'])->name('payroll.lines.destroy');
    Route::get('/payroll/periods/{accounting_payroll_period}/lines/{accounting_payroll_line}/payslip', [LoanAccountingBooksController::class, 'payslip'])->name('payroll.lines.payslip');

    Route::get('/budget/report', [LoanAccountingBooksController::class, 'budgetReport'])->name('budget.report');
    Route::get('/budget/lines/create', [LoanAccountingBooksController::class, 'budgetCreate'])->name('budget.create');
    Route::post('/budget/lines', [LoanAccountingBooksController::class, 'budgetStore'])->name('budget.store');
    Route::get('/budget/lines', [LoanAccountingBooksController::class, 'budgetIndex'])->name('budget.index');
    Route::get('/budget/lines/{accounting_budget_line}/edit', [LoanAccountingBooksController::class, 'budgetEdit'])->name('budget.edit');
    Route::patch('/budget/lines/{accounting_budget_line}', [LoanAccountingBooksController::class, 'budgetUpdate'])->name('budget.update');
    Route::delete('/budget/lines/{accounting_budget_line}', [LoanAccountingBooksController::class, 'budgetDestroy'])->name('budget.destroy');

    Route::get('/reconciliation/create', [LoanAccountingBooksController::class, 'reconciliationCreate'])->name('reconciliation.create');
    Route::post('/reconciliation', [LoanAccountingBooksController::class, 'reconciliationStore'])->name('reconciliation.store');
    Route::get('/reconciliation', [LoanAccountingBooksController::class, 'reconciliationIndex'])->name('reconciliation.index');
    Route::get('/reconciliation/{accounting_bank_reconciliation}/edit', [LoanAccountingBooksController::class, 'reconciliationEdit'])->name('reconciliation.edit');
    Route::patch('/reconciliation/{accounting_bank_reconciliation}', [LoanAccountingBooksController::class, 'reconciliationUpdate'])->name('reconciliation.update');
    Route::delete('/reconciliation/{accounting_bank_reconciliation}', [LoanAccountingBooksController::class, 'reconciliationDestroy'])->name('reconciliation.destroy');

    Route::get('/chart-of-accounts/create', [LoanAccountingController::class, 'chartCreate'])->name('chart.create');
    Route::post('/chart-of-accounts', [LoanAccountingController::class, 'chartStore'])->name('chart.store');
    Route::get('/chart-of-accounts/next-code', [LoanAccountingController::class, 'chartNextCode'])->name('chart.next_code');
    Route::post('/chart-of-accounts/wallet-slots', [LoanAccountingController::class, 'chartWalletSlotsUpdate'])->name('chart.wallet_slots.update');
    Route::post('/chart-of-accounts/posting-rules', [LoanAccountingController::class, 'chartPostingRuleStore'])->name('chart.posting_rules.store');
    Route::patch('/chart-of-accounts/posting-rules/{accounting_posting_rule}', [LoanAccountingController::class, 'chartPostingRuleUpdate'])->name('chart.posting_rules.update');
    Route::delete('/chart-of-accounts/posting-rules/{accounting_posting_rule}', [LoanAccountingController::class, 'chartPostingRuleDestroy'])->name('chart.posting_rules.destroy');
    Route::patch('/chart-of-accounts/event-mappings/{eventKey}', [LoanAccountingBooksController::class, 'updateEventMapping'])->name('chart.event_mappings.update');
    Route::get('/chart-of-accounts/{accounting_chart_account}/edit', [LoanAccountingController::class, 'chartEdit'])->name('chart.edit');
    Route::patch('/chart-of-accounts/{accounting_chart_account}', [LoanAccountingController::class, 'chartUpdate'])->name('chart.update');
    Route::delete('/chart-of-accounts/{accounting_chart_account}', [LoanAccountingController::class, 'chartDestroy'])->name('chart.destroy');

    Route::get('/journal-entries/create', [LoanAccountingController::class, 'journalCreate'])->name('journal.create');
    Route::get('/journal-entries/{accounting_journal_entry}/edit', [LoanAccountingController::class, 'journalEdit'])->name('journal.edit');
    Route::post('/journal-entries', [LoanAccountingController::class, 'journalStore'])->name('journal.store');
    Route::post('/journal-entries/templates', [LoanAccountingController::class, 'journalTemplateStore'])->name('journal.templates.store');
    Route::post('/journal-entries/templates/{accounting_journal_template}', [LoanAccountingController::class, 'journalTemplateUpdate'])->name('journal.templates.update');
    Route::post('/journal-entries/templates/{accounting_journal_template}/delete', [LoanAccountingController::class, 'journalTemplateDestroy'])->name('journal.templates.destroy');
    Route::get('/journal-entries/approval-queue', [LoanAccountingBooksController::class, 'journalApprovalQueue'])->name('journal.approval_queue');
    Route::post('/journal-entries/approval-queue/{approval_queue}/approve', [LoanAccountingBooksController::class, 'approvePendingJournal'])->name('journal.approval_queue.approve');
    Route::post('/journal-entries/approval-queue/{approval_queue}/reject', [LoanAccountingBooksController::class, 'rejectPendingJournal'])->name('journal.approval_queue.reject');
    Route::get('/journal-entries', [LoanAccountingController::class, 'journalIndex'])->name('journal.index');
    Route::post('/journal-entries/bulk', [LoanAccountingController::class, 'journalBulk'])->name('journal.bulk');
    Route::get('/journal-entries/{accounting_journal_entry}', [LoanAccountingController::class, 'journalShow'])->name('journal.show');
    Route::post('/journal-entries/{accounting_journal_entry}/reverse', [LoanAccountingController::class, 'journalReverse'])->name('journal.reverse');
    Route::delete('/journal-entries/{accounting_journal_entry}', [LoanAccountingController::class, 'journalDestroy'])->name('journal.destroy');

    Route::get('/ledger', [LoanAccountingController::class, 'ledger'])->name('ledger');

    Route::get('/requisitions/create', [LoanAccountingController::class, 'requisitionsCreate'])->name('requisitions.create');
    Route::post('/requisitions', [LoanAccountingController::class, 'requisitionsStore'])->name('requisitions.store');
    Route::get('/requisitions', [LoanAccountingController::class, 'requisitionsIndex'])->name('requisitions.index');
    Route::post('/requisitions/bulk', [LoanAccountingController::class, 'requisitionsBulk'])->name('requisitions.bulk');
    Route::get('/requisitions/{accounting_requisition}/edit', [LoanAccountingController::class, 'requisitionsEdit'])->name('requisitions.edit');
    Route::patch('/requisitions/{accounting_requisition}', [LoanAccountingController::class, 'requisitionsUpdate'])->name('requisitions.update');
    Route::delete('/requisitions/{accounting_requisition}', [LoanAccountingController::class, 'requisitionsDestroy'])->name('requisitions.destroy');
    Route::post('/requisitions/{accounting_requisition}/approve', [LoanAccountingController::class, 'requisitionsApprove'])->name('requisitions.approve');
    Route::post('/requisitions/{accounting_requisition}/reject', [LoanAccountingController::class, 'requisitionsReject'])->name('requisitions.reject');
    Route::post('/requisitions/{accounting_requisition}/pay', [LoanAccountingController::class, 'requisitionsPay'])->name('requisitions.pay');

    Route::get('/utility-payments/create', [LoanAccountingController::class, 'utilitiesCreate'])->name('utilities.create');
    Route::post('/utility-payments', [LoanAccountingController::class, 'utilitiesStore'])->name('utilities.store');
    Route::get('/utility-payments', [LoanAccountingController::class, 'utilitiesIndex'])->name('utilities.index');
    Route::post('/utility-payments/bulk', [LoanAccountingController::class, 'utilitiesBulk'])->name('utilities.bulk');
    Route::get('/utility-payments/{accounting_utility_payment}/edit', [LoanAccountingController::class, 'utilitiesEdit'])->name('utilities.edit');
    Route::patch('/utility-payments/{accounting_utility_payment}', [LoanAccountingController::class, 'utilitiesUpdate'])->name('utilities.update');
    Route::delete('/utility-payments/{accounting_utility_payment}', [LoanAccountingController::class, 'utilitiesDestroy'])->name('utilities.destroy');

    Route::get('/petty-cash/create', [LoanAccountingController::class, 'pettyCreate'])->name('petty.create');
    Route::post('/petty-cash', [LoanAccountingController::class, 'pettyStore'])->name('petty.store');
    Route::get('/petty-cash', [LoanAccountingController::class, 'pettyIndex'])->name('petty.index');
    Route::post('/petty-cash/bulk', [LoanAccountingController::class, 'pettyBulk'])->name('petty.bulk');
    Route::get('/petty-cash/{accounting_petty_cash_entry}/edit', [LoanAccountingController::class, 'pettyEdit'])->name('petty.edit');
    Route::patch('/petty-cash/{accounting_petty_cash_entry}', [LoanAccountingController::class, 'pettyUpdate'])->name('petty.update');
    Route::delete('/petty-cash/{accounting_petty_cash_entry}', [LoanAccountingController::class, 'pettyDestroy'])->name('petty.destroy');

    Route::get('/salary-advances/create', [LoanAccountingController::class, 'advancesCreate'])->name('advances.create');
    Route::post('/salary-advances', [LoanAccountingController::class, 'advancesStore'])->name('advances.store');
    Route::get('/salary-advances', [LoanAccountingController::class, 'advancesIndex'])->name('advances.index');
    Route::post('/salary-advances/bulk', [LoanAccountingController::class, 'advancesBulk'])->name('advances.bulk');
    Route::get('/salary-advances/{accounting_salary_advance}/edit', [LoanAccountingController::class, 'advancesEdit'])->name('advances.edit');
    Route::patch('/salary-advances/{accounting_salary_advance}', [LoanAccountingController::class, 'advancesUpdate'])->name('advances.update');
    Route::delete('/salary-advances/{accounting_salary_advance}', [LoanAccountingController::class, 'advancesDestroy'])->name('advances.destroy');
    Route::post('/salary-advances/{accounting_salary_advance}/approve', [LoanAccountingController::class, 'advancesApprove'])->name('advances.approve');
    Route::post('/salary-advances/{accounting_salary_advance}/reject', [LoanAccountingController::class, 'advancesReject'])->name('advances.reject');
    Route::post('/salary-advances/{accounting_salary_advance}/settle', [LoanAccountingController::class, 'advancesSettle'])->name('advances.settle');
});

Route::prefix('loan/book')->name('loan.book.')->group(function () {
    // Must stay before `disbursements/{loan_book_disbursement}`; `whereNumber` on that segment prevents "create" binding as an id.
    Route::get('/disbursements/create', [LoanBookOperationsController::class, 'disbursementsCreate'])->name('disbursements.create');
    Route::get('/disbursements/record', function () {
        return redirect()->route('loan.book.disbursements.create', request()->query());
    });

    Route::get('/app-loans-report', [LoanBookApplicationsController::class, 'report'])->name('app_loans_report');
    Route::get('/applications/create', [LoanBookApplicationsController::class, 'create'])->name('applications.create');
    Route::post('/applications/products', [LoanBookApplicationsController::class, 'storeProduct'])->name('applications.products.store');
    Route::get('/applications/suspense-options', [LoanBookApplicationsController::class, 'suspenseOptions'])->name('applications.suspense_options');
    Route::post('/applications', [LoanBookApplicationsController::class, 'store'])->name('applications.store');
    Route::patch('/applications/{loan_book_application}/stage', [LoanBookApplicationsController::class, 'updateStage'])->name('applications.update_stage');
    Route::get('/applications', [LoanBookApplicationsController::class, 'index'])->name('applications.index');
    Route::get('/applications/{loan_book_application}', [LoanBookApplicationsController::class, 'show'])->name('applications.show');
    Route::get('/applications/{loan_book_application}/edit', [LoanBookApplicationsController::class, 'edit'])->name('applications.edit');
    Route::patch('/applications/{loan_book_application}', [LoanBookApplicationsController::class, 'update'])->name('applications.update');
    Route::delete('/applications/{loan_book_application}', [LoanBookApplicationsController::class, 'destroy'])->name('applications.destroy');

    Route::get('/loans/create', [LoanBookLoansController::class, 'create'])->name('loans.create');
    Route::post('/loans', [LoanBookLoansController::class, 'store'])->name('loans.store');
    Route::post('/loans/quick-branch', [LoanBookLoansController::class, 'quickBranchStore'])->name('loans.quick_branch');
    Route::post('/loans/sync-schedules', [LoanBookLoansController::class, 'syncSchedulesFromApplicationsBulk'])->name('loans.sync_schedules_bulk');
    Route::post('/loans/rebuild-snapshots', [LoanBookLoansController::class, 'rebuildSnapshotsBulk'])->name('loans.rebuild_snapshots_bulk');
    Route::get('/loans', [LoanBookLoansController::class, 'index'])->name('loans.index');
    Route::get('/loan-arrears', [LoanBookLoansController::class, 'arrears'])->name('loan_arrears');
    Route::post('/loan-arrears/send-sms', [LoanBookLoansController::class, 'arrearsSendSms'])->name('loan_arrears.send_sms');
    Route::get('/checkoff-loans', [LoanBookLoansController::class, 'checkoff'])->name('checkoff_loans');
    Route::get('/loans/{loan_book_loan}', [LoanBookLoansController::class, 'show'])->name('loans.show');
    Route::post('/loans/{loan_book_loan}/rebuild-snapshot', [LoanBookLoansController::class, 'rebuildSnapshot'])->name('loans.rebuild_snapshot');
    Route::post('/loans/{loan_book_loan}/sync-schedule', [LoanBookLoansController::class, 'syncScheduleFromApplication'])->name('loans.sync_schedule');
    Route::get('/loans/{loan_book_loan}/edit', [LoanBookLoansController::class, 'edit'])->name('loans.edit');
    Route::patch('/loans/{loan_book_loan}', [LoanBookLoansController::class, 'update'])->name('loans.update');
    Route::delete('/loans/{loan_book_loan}', [LoanBookLoansController::class, 'destroy'])->name('loans.destroy');

    Route::post('/disbursements', [LoanBookOperationsController::class, 'disbursementsStore'])->name('disbursements.store');
    Route::get('/disbursements', [LoanBookOperationsController::class, 'disbursementsIndex'])->name('disbursements.index');
    Route::get('/disbursements/{loan_book_disbursement}', [LoanBookOperationsController::class, 'disbursementsShow'])
        ->whereNumber('loan_book_disbursement')
        ->name('disbursements.show');
    Route::post('/disbursements/{loan_book_disbursement}/retry-payout', [LoanBookOperationsController::class, 'disbursementsRetryPayout'])
        ->whereNumber('loan_book_disbursement')
        ->name('disbursements.retry_payout');
    Route::delete('/disbursements/{loan_book_disbursement}', [LoanBookOperationsController::class, 'disbursementsDestroy'])
        ->whereNumber('loan_book_disbursement')
        ->name('disbursements.destroy');

    Route::get('/collection-sheet', [LoanBookOperationsController::class, 'collectionSheet'])->name('collection_sheet.index');
    Route::post('/collection-sheet', [LoanBookOperationsController::class, 'collectionSheetStore'])->name('collection_sheet.store');
    Route::delete('/collection-sheet/{loan_book_collection_entry}', [LoanBookOperationsController::class, 'collectionSheetDestroy'])->name('collection_sheet.destroy');

    Route::get('/collection-mtd', [LoanBookOperationsController::class, 'collectionMtd'])->name('collection_mtd');
    Route::get('/collection-reports', [LoanBookOperationsController::class, 'collectionReports'])->name('collection_reports');
    Route::get('/collections-reports', [LoanBookOperationsController::class, 'collectionsReportsCommandCenter'])->name('collections_reports');

    Route::get('/collection-agents/create', [LoanBookOperationsController::class, 'agentsCreate'])->name('collection_agents.create');
    Route::post('/collection-agents', [LoanBookOperationsController::class, 'agentsStore'])->name('collection_agents.store');
    Route::get('/collection-agents', [LoanBookOperationsController::class, 'agentsIndex'])->name('collection_agents.index');
    Route::get('/collection-agents/{loan_book_agent}/edit', [LoanBookOperationsController::class, 'agentsEdit'])->name('collection_agents.edit');
    Route::patch('/collection-agents/{loan_book_agent}', [LoanBookOperationsController::class, 'agentsUpdate'])->name('collection_agents.update');
    Route::delete('/collection-agents/{loan_book_agent}', [LoanBookOperationsController::class, 'agentsDestroy'])->name('collection_agents.destroy');

    Route::get('/collection-rates/create', [LoanBookOperationsController::class, 'ratesCreate'])->name('collection_rates.create');
    Route::post('/collection-rates', [LoanBookOperationsController::class, 'ratesStore'])->name('collection_rates.store');
    Route::get('/collection-rates', [LoanBookOperationsController::class, 'ratesIndex'])->name('collection_rates.index');
    Route::get('/collection-rates/{loan_book_collection_rate}/edit', [LoanBookOperationsController::class, 'ratesEdit'])->name('collection_rates.edit');
    Route::patch('/collection-rates/{loan_book_collection_rate}', [LoanBookOperationsController::class, 'ratesUpdate'])->name('collection_rates.update');
    Route::delete('/collection-rates/{loan_book_collection_rate}', [LoanBookOperationsController::class, 'ratesDestroy'])->name('collection_rates.destroy');
});

Route::prefix('loan/asset-financing')->name('loan.assets.')->group(function () {
    Route::get('/measurement-units/create', [LoanAssetFinancingController::class, 'unitsCreate'])->name('units.create');
    Route::post('/measurement-units', [LoanAssetFinancingController::class, 'unitsStore'])->name('units.store');
    Route::get('/measurement-units', [LoanAssetFinancingController::class, 'unitsIndex'])->name('units.index');
    Route::get('/measurement-units/{loan_asset_measurement_unit}/edit', [LoanAssetFinancingController::class, 'unitsEdit'])->name('units.edit');
    Route::patch('/measurement-units/{loan_asset_measurement_unit}', [LoanAssetFinancingController::class, 'unitsUpdate'])->name('units.update');
    Route::delete('/measurement-units/{loan_asset_measurement_unit}', [LoanAssetFinancingController::class, 'unitsDestroy'])->name('units.destroy');

    Route::get('/categories/create', [LoanAssetFinancingController::class, 'categoriesCreate'])->name('categories.create');
    Route::post('/categories', [LoanAssetFinancingController::class, 'categoriesStore'])->name('categories.store');
    Route::get('/categories', [LoanAssetFinancingController::class, 'categoriesIndex'])->name('categories.index');
    Route::get('/categories/{loan_asset_category}/edit', [LoanAssetFinancingController::class, 'categoriesEdit'])->name('categories.edit');
    Route::patch('/categories/{loan_asset_category}', [LoanAssetFinancingController::class, 'categoriesUpdate'])->name('categories.update');
    Route::delete('/categories/{loan_asset_category}', [LoanAssetFinancingController::class, 'categoriesDestroy'])->name('categories.destroy');

    Route::get('/stock/create', [LoanAssetFinancingController::class, 'itemsCreate'])->name('items.create');
    Route::post('/stock', [LoanAssetFinancingController::class, 'itemsStore'])->name('items.store');
    Route::get('/stock', [LoanAssetFinancingController::class, 'itemsIndex'])->name('items.index');
    Route::get('/stock/{loan_asset_stock_item}/edit', [LoanAssetFinancingController::class, 'itemsEdit'])->name('items.edit');
    Route::patch('/stock/{loan_asset_stock_item}', [LoanAssetFinancingController::class, 'itemsUpdate'])->name('items.update');
    Route::delete('/stock/{loan_asset_stock_item}', [LoanAssetFinancingController::class, 'itemsDestroy'])->name('items.destroy');
});

Route::prefix('loan/payments')->name('loan.payments.')->group(function () {
    Route::get('/unposted', [LoanPaymentsController::class, 'unposted'])->name('unposted');
    Route::get('/unposted/print', [LoanPaymentsController::class, 'unpostedPrint'])->name('unposted.print');
    Route::get('/processed', [LoanPaymentsController::class, 'processed'])->name('processed');
    Route::get('/processed/print', [LoanPaymentsController::class, 'processedPrint'])->name('processed.print');
    Route::get('/prepayments', [LoanPaymentsController::class, 'prepayments'])->name('prepayments');
    Route::get('/overpayments', [LoanPaymentsController::class, 'overpayments'])->name('overpayments');
    Route::get('/merged', [LoanPaymentsController::class, 'merged'])->name('merged');
    Route::get('/c2b-reversals', [LoanPaymentsController::class, 'c2bReversals'])->name('c2b_reversals');
    Route::get('/receipts', [LoanPaymentsController::class, 'receipts'])->name('receipts');
    Route::get('/payin-summary', [LoanPaymentsController::class, 'payinSummary'])->name('payin_summary');
    Route::get('/report/export', [LoanPaymentsController::class, 'reportExport'])->name('report.export');
    Route::get('/report', [LoanPaymentsController::class, 'report'])->name('report');
    Route::get('/validate', [LoanPaymentsController::class, 'validateForm'])->name('validate');
    Route::post('/validate', [LoanPaymentsController::class, 'validateStore'])->name('validate.store');
    Route::post('/{loan_book_payment}/validate', [LoanPaymentsController::class, 'validateSingle'])->name('validate.single');
    Route::get('/merge', [LoanPaymentsController::class, 'mergeForm'])->name('merge');
    Route::post('/merge', [LoanPaymentsController::class, 'mergeStore'])->name('merge.store');
    Route::get('/reversal/create', [LoanPaymentsController::class, 'reversalCreate'])->name('reversal.create');
    Route::post('/reversal', [LoanPaymentsController::class, 'reversalStore'])->name('reversal.store');
    Route::get('/create', [LoanPaymentsController::class, 'create'])->name('create');
    Route::post('/', [LoanPaymentsController::class, 'store'])->name('store');
    Route::post('/unposted/auto-match', [LoanPaymentsController::class, 'autoMatch'])->name('unposted.auto_match');
    Route::post('/{loan_book_payment}/assign-loan', [LoanPaymentsController::class, 'assignLoan'])->name('assign_loan');
    Route::post('/{loan_book_payment}/post', [LoanPaymentsController::class, 'post'])->name('post');
    Route::get('/{loan_book_payment}', [LoanPaymentsController::class, 'show'])->name('show');
    Route::get('/{loan_book_payment}/edit', [LoanPaymentsController::class, 'edit'])->name('edit');
    Route::patch('/{loan_book_payment}', [LoanPaymentsController::class, 'update'])->name('update');
    Route::delete('/{loan_book_payment}', [LoanPaymentsController::class, 'destroy'])->name('destroy');
});

Route::prefix('loan/bulk-sms')->middleware('loan.permission:bulksms.view')->name('loan.bulksms.')->group(function () {
    Route::get('/compose', fn () => redirect()->route('loan.communications.messages'))->name('compose');
    Route::post('/compose', [LoanBulkSmsController::class, 'composeStore'])->middleware('loan.permission:bulksms.create')->name('compose.store');
    Route::get('/templates/create', fn () => redirect()->route('loan.communications.templates'))->name('templates.create');
    Route::post('/templates', [LoanBulkSmsController::class, 'templatesStore'])->middleware('loan.permission:bulksms.create')->name('templates.store');
    Route::get('/templates', fn () => redirect()->route('loan.communications.templates'))->name('templates.index');
    Route::get('/templates/{sms_template}/edit', [LoanBulkSmsController::class, 'templatesEdit'])->middleware('loan.permission:bulksms.update')->name('templates.edit');
    Route::patch('/templates/{sms_template}', [LoanBulkSmsController::class, 'templatesUpdate'])->middleware('loan.permission:bulksms.update')->name('templates.update');
    Route::delete('/templates/{sms_template}', [LoanBulkSmsController::class, 'templatesDestroy'])->middleware('loan.permission:bulksms.delete')->name('templates.destroy');
    Route::get('/logs', fn () => redirect()->route('loan.communications.messages'))->name('logs');
    Route::get('/wallet', fn () => redirect()->route('loan.communications.sms_provider'))->name('wallet');
    Route::post('/wallet/topup', [LoanBulkSmsController::class, 'walletTopup'])->middleware('loan.permission:bulksms.approve')->name('wallet.topup');
    Route::get('/schedules', [LoanBulkSmsController::class, 'schedules'])->name('schedules');
    Route::post('/schedules/{sms_schedule}/cancel', [LoanBulkSmsController::class, 'schedulesCancel'])->middleware('loan.permission:bulksms.delete')->name('schedules.cancel');
});

Route::prefix('loan/communications')->middleware('loan.permission:bulksms.view')->name('loan.communications.')->group(function () {
    Route::get('/notifications', [LoanCommunicationsWebController::class, 'notifications'])->name('notifications');
    Route::get('/notifications/export', [LoanCommunicationsWebController::class, 'notificationsExport'])->name('notifications.export');
    Route::post('/notifications/bulk', [LoanCommunicationsWebController::class, 'notificationsBulk'])->middleware('loan.permission:bulksms.create')->name('notifications.bulk');
    Route::post('/notifications/mark-all-read', [LoanCommunicationsWebController::class, 'notificationsMarkAllRead'])->name('notifications.mark_all_read');
    Route::get('/notifications/{log}', [LoanCommunicationsWebController::class, 'showNotification'])->name('notifications.show');
    Route::get('/messages', [LoanCommunicationsWebController::class, 'messages'])->name('messages');
    Route::get('/messages/export', [LoanCommunicationsWebController::class, 'messagesExport'])->name('messages.export');
    Route::post('/messages/bulk', [LoanCommunicationsWebController::class, 'messagesBulk'])->middleware('loan.permission:bulksms.create')->name('messages.bulk');
    Route::post('/sms-topup', [LoanCommunicationsWebController::class, 'smsWalletTopup'])->middleware('loan.permission:bulksms.create')->name('sms_topup');
    Route::get('/sms/balance', [LoanCommunicationsWebController::class, 'smsBalanceJson'])->name('sms_balance');
    Route::get('/sms/topup-status', [LoanCommunicationsWebController::class, 'smsTopupStatusJson'])->middleware('loan.permission:bulksms.create')->name('sms_topup_status');
    Route::get('/sms/provider', [LoanCommunicationsWebController::class, 'smsProvider'])->name('sms_provider');
    Route::post('/messages/{log}/resend', [LoanCommunicationsWebController::class, 'resendMessage'])->middleware('loan.permission:bulksms.create')->name('messages.resend');
    Route::get('/messages/{log}', [LoanCommunicationsWebController::class, 'showMessage'])->name('messages.show');
    Route::post('/messages', [LoanCommunicationsWebController::class, 'logMessage'])->middleware('loan.permission:bulksms.create')->name('messages.store');
    Route::get('/bulk', [LoanCommunicationsWebController::class, 'bulk'])->name('bulk');
    Route::get('/bulk/export', [LoanCommunicationsWebController::class, 'bulkExport'])->name('bulk.export');
    Route::post('/bulk', [LoanCommunicationsWebController::class, 'logBulk'])->middleware('loan.permission:bulksms.create')->name('bulk.store');
    Route::get('/recipients', [LoanCommunicationsWebController::class, 'recipients'])->middleware('loan.permission:bulksms.create')->name('recipients');
    Route::get('/templates', [LoanCommunicationsWebController::class, 'templates'])->name('templates');
    Route::post('/templates', [LoanCommunicationsWebController::class, 'storeTemplate'])->middleware('loan.permission:bulksms.create')->name('templates.store');
    Route::delete('/templates/{template}', [LoanCommunicationsWebController::class, 'destroyTemplate'])->middleware('loan.permission:bulksms.create')->name('templates.destroy');
    Route::get('/payment-templates', [LoanCommunicationsWebController::class, 'rentTemplates'])->name('payment_templates');
    Route::post('/payment-templates', [LoanCommunicationsWebController::class, 'saveRentTemplateMessages'])->middleware('loan.permission:bulksms.create')->name('payment_templates.store');
    Route::post('/payment-templates/preview', [LoanCommunicationsWebController::class, 'previewRentTemplatesJson'])->name('payment_templates.preview');
    Route::get('/conversations', [LoanCommunicationsWebController::class, 'conversationsPage'])->middleware('loan.permission:bulksms.create')->name('conversations');
    Route::get('/conversations-data', [LoanCommunicationsWebController::class, 'conversations'])->middleware('loan.permission:bulksms.create')->name('conversations.data');
    Route::get('/conversations/{conversation}', [LoanCommunicationsWebController::class, 'showConversation'])->middleware('loan.permission:bulksms.create')->name('conversations.show');
    Route::post('/conversations/{conversation}/reply', [LoanCommunicationsWebController::class, 'replyConversation'])->middleware('loan.permission:bulksms.create')->name('conversations.reply');
    Route::get('/', fn () => view('loan.communications.index'))->name('index');
});

Route::prefix('loan/system-help')->middleware('loan.permission:system.help.view')->name('loan.system.')->group(function () {
    Route::get('/tickets/create', [LoanSystemHelpController::class, 'ticketsCreate'])->middleware('loan.permission:system.help.create')->name('tickets.create');
    Route::post('/tickets', [LoanSystemHelpController::class, 'ticketsStore'])->middleware('loan.permission:system.help.create')->name('tickets.store');
    Route::get('/tickets', [LoanSystemHelpController::class, 'ticketsIndex'])->name('tickets.index');
    Route::get('/tickets/{loan_support_ticket}', [LoanSystemHelpController::class, 'ticketsShow'])->name('tickets.show');
    Route::get('/tickets/{loan_support_ticket}/edit', [LoanSystemHelpController::class, 'ticketsEdit'])->middleware('loan.permission:system.help.update')->name('tickets.edit');
    Route::patch('/tickets/{loan_support_ticket}', [LoanSystemHelpController::class, 'ticketsUpdate'])->middleware('loan.permission:system.help.update')->name('tickets.update');
    Route::delete('/tickets/{loan_support_ticket}', [LoanSystemHelpController::class, 'ticketsDestroy'])->middleware('loan.permission:system.help.delete')->name('tickets.destroy');
    Route::post('/tickets/{loan_support_ticket}/replies', [LoanSystemHelpController::class, 'ticketsReplyStore'])->middleware('loan.permission:system.help.update')->name('tickets.replies.store');
    Route::patch('/tickets/{loan_support_ticket}/status', [LoanSystemHelpController::class, 'ticketsStatusUpdate'])->middleware('loan.permission:system.help.update')->name('tickets.status');

    Route::get('/setup', [LoanSystemHelpController::class, 'setupHub'])->name('setup');
    Route::get('/setup/company', [LoanSystemHelpController::class, 'setupCompany'])->name('setup.company');
    Route::post('/setup/company', [LoanSystemHelpController::class, 'setupCompanyUpdate'])->middleware('loan.permission:system.help.configure')->name('setup.company.update');
    Route::get('/setup/preferences', [LoanSystemHelpController::class, 'setupPreferences'])->name('setup.preferences');
    Route::post('/setup/preferences', [LoanSystemHelpController::class, 'setupPreferencesUpdate'])->middleware('loan.permission:system.help.configure')->name('setup.preferences.update');
    Route::get('/setup/client-settings', [LoanSystemHelpController::class, 'setupClientSettings'])->name('setup.client_settings');
    Route::post('/setup/client-settings', [LoanSystemHelpController::class, 'setupClientSettingsUpdate'])->middleware('loan.permission:system.help.configure')->name('setup.client_settings.update');
    Route::get('/setup/loan-products', [LoanSystemHelpController::class, 'setupLoanProducts'])->name('setup.loan_products');
    Route::get('/setup/loan-products/create', [LoanSystemHelpController::class, 'setupLoanProductsCreate'])->middleware('loan.permission:system.help.create')->name('setup.loan_products.create');
    Route::get('/setup/loan-products/{loan_product}', [LoanSystemHelpController::class, 'setupLoanProductsShow'])->name('setup.loan_products.show');
    Route::post('/setup/loan-products', [LoanSystemHelpController::class, 'setupLoanProductsStore'])->middleware('loan.permission:system.help.create')->name('setup.loan_products.store');
    Route::patch('/setup/loan-products/{loan_product}', [LoanSystemHelpController::class, 'setupLoanProductsUpdate'])->middleware('loan.permission:system.help.update')->name('setup.loan_products.update');
    Route::delete('/setup/loan-products/{loan_product}', [LoanSystemHelpController::class, 'setupLoanProductsDestroy'])->middleware('loan.permission:system.help.delete')->name('setup.loan_products.destroy');
    Route::get('/setup/departments', [LoanSystemHelpController::class, 'setupDepartments'])->name('setup.departments');
    Route::post('/setup/departments', [LoanSystemHelpController::class, 'setupDepartmentsStore'])->middleware('loan.permission:system.help.create')->name('setup.departments.store');
    Route::post('/setup/departments/sync', [LoanSystemHelpController::class, 'setupDepartmentsSync'])->middleware('loan.permission:system.help.configure')->name('setup.departments.sync');
    Route::patch('/setup/departments/{loan_department}', [LoanSystemHelpController::class, 'setupDepartmentsUpdate'])->middleware('loan.permission:system.help.update')->name('setup.departments.update');
    Route::delete('/setup/departments/{loan_department}', [LoanSystemHelpController::class, 'setupDepartmentsDestroy'])->middleware('loan.permission:system.help.delete')->name('setup.departments.destroy');
    Route::get('/setup/job-titles', [LoanSystemHelpController::class, 'setupJobTitles'])->name('setup.job_titles');
    Route::post('/setup/job-titles', [LoanSystemHelpController::class, 'setupJobTitlesStore'])->middleware('loan.permission:system.help.create')->name('setup.job_titles.store');
    Route::post('/setup/job-titles/sync', [LoanSystemHelpController::class, 'setupJobTitlesSync'])->middleware('loan.permission:system.help.configure')->name('setup.job_titles.sync');
    Route::patch('/setup/job-titles/{loan_job_title}', [LoanSystemHelpController::class, 'setupJobTitlesUpdate'])->middleware('loan.permission:system.help.update')->name('setup.job_titles.update');
    Route::delete('/setup/job-titles/{loan_job_title}', [LoanSystemHelpController::class, 'setupJobTitlesDestroy'])->middleware('loan.permission:system.help.delete')->name('setup.job_titles.destroy');
    Route::get('/setup/access-roles', [LoanSystemHelpController::class, 'setupAccessRoles'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.view'])->name('setup.access_roles');
    Route::post('/setup/access-roles/sync', [LoanSystemHelpController::class, 'setupAccessRolesSync'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.configure'])->name('setup.access_roles.sync');
    Route::post('/setup/access-roles', [LoanSystemHelpController::class, 'setupAccessRolesStore'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.configure'])->name('setup.access_roles.store');
    Route::post('/setup/access-roles/{loan_role}/clone', [LoanSystemHelpController::class, 'setupAccessRolesClone'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.configure'])->name('setup.access_roles.clone');
    Route::patch('/setup/access-roles/{loan_role}', [LoanSystemHelpController::class, 'setupAccessRolesUpdate'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.configure'])->name('setup.access_roles.update');
    Route::post('/setup/access-roles/{loan_role}/assign', [LoanSystemHelpController::class, 'setupAccessRolesAssign'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.configure'])->name('setup.access_roles.assign');
    Route::delete('/setup/access-roles/{loan_role}', [LoanSystemHelpController::class, 'setupAccessRolesDestroy'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.configure'])->name('setup.access_roles.destroy');
    Route::post('/setup/access-roles/security-policies', [LoanSystemHelpController::class, 'setupAccessSecurityPoliciesUpdate'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.configure'])->name('setup.access_roles.security_policies.update');
    Route::post('/setup/access-roles/temporary-access', [LoanSystemHelpController::class, 'setupTemporaryAccessRequestStore'])->middleware('loan.permission:access_roles.request')->name('setup.access_roles.temporary_access.store');
    Route::post('/setup/access-roles/temporary-access/{loan_temporary_access_request}/decision', [LoanSystemHelpController::class, 'setupTemporaryAccessRequestDecision'])->middleware(['loan.role:admin,manager', 'loan.permission:access_roles.approve'])->name('setup.access_roles.temporary_access.decision');
    Route::post('/setup/access-roles/devices/{user}/unbind', [LoanSystemHelpController::class, 'setupDeviceUnbind'])->middleware(['loan.role:admin,manager', 'loan.permission:device_governance.unbind'])->name('setup.access_roles.devices.unbind');

    Route::get('/setup/loan-form/client', [LoanFormSetupController::class, 'clientForm'])->name('form_setup.client');
    Route::post('/setup/loan-form/client', [LoanFormSetupController::class, 'clientFormSave'])->middleware('loan.permission:system.help.configure')->name('form_setup.client.save');
    Route::get('/setup/loan-form/staff', [LoanFormSetupController::class, 'staffForm'])->name('form_setup.staff');
    Route::post('/setup/loan-form/staff', [LoanFormSetupController::class, 'staffFormSave'])->middleware('loan.permission:system.help.configure')->name('form_setup.staff.save');

    Route::get('/setup/salary-advance-form', [LoanFormSetupController::class, 'salaryAdvanceForm'])->name('form_setup.salary_advance');
    Route::post('/setup/salary-advance-form', [LoanFormSetupController::class, 'salaryAdvanceFormSave'])->middleware('loan.permission:system.help.configure')->name('form_setup.salary_advance.save');

    Route::get('/setup/forms/loan-settings/loan-form-editor-payload', [LoanFormSetupController::class, 'loanFormEditorPayload'])
        ->name('form_setup.loan_form_editor_payload');

    Route::get('/setup/forms/{page}', [LoanFormSetupController::class, 'setupPage'])
        ->where('page', LoanFormSetupController::FORM_SETUP_PAGE_PATTERN)
        ->name('form_setup.page');
    Route::post('/setup/forms/{page}', [LoanFormSetupController::class, 'setupPageSave'])
        ->middleware('loan.permission:system.help.configure')
        ->where('page', LoanFormSetupController::FORM_SETUP_PAGE_PATTERN)
        ->name('form_setup.page.save');

    Route::get('/access-logs', [LoanSystemHelpController::class, 'accessLogsIndex'])->middleware('loan.permission:audit_logs.view')->name('access_logs.index');
    Route::post('/access-logs/{loan_access_log}/concerns', [LoanSystemHelpController::class, 'accessLogsConcernStore'])->middleware('loan.permission:audit_logs.create')->name('access_logs.concerns.store');
});
