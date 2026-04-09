<?php

use App\Http\Controllers\Loan\LoanAccountController;
use App\Http\Controllers\Loan\LoanAccountingBooksController;
use App\Http\Controllers\Loan\LoanAccountingController;
use App\Http\Controllers\Loan\LoanAssetFinancingController;
use App\Http\Controllers\Loan\LoanBookApplicationsController;
use App\Http\Controllers\Loan\LoanBookLoansController;
use App\Http\Controllers\Loan\LoanBookOperationsController;
use App\Http\Controllers\Loan\LoanBulkSmsController;
use App\Http\Controllers\Loan\LoanBusinessAnalyticsController;
use App\Http\Controllers\Loan\LoanClientsController;
use App\Http\Controllers\Loan\LoanDashboardController;
use App\Http\Controllers\Loan\LoanEmployeesController;
use App\Http\Controllers\Loan\LoanFinancialController;
use App\Http\Controllers\Loan\LoanFormSetupController;
use App\Http\Controllers\Loan\LoanOrganizationController;
use App\Http\Controllers\Loan\LoanPaymentsController;
use App\Http\Controllers\Loan\LoanSystemHelpController;
use App\Http\Controllers\Integrations\MpesaDarajaWebhookController;
use App\Http\Controllers\Property\PropertyPaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Auth\ChooseModuleController;
use App\Http\Controllers\SuperAdmin\SuperAdminConsoleController;
use App\Http\Controllers\SuperAdmin\SuperAdminUserController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('public.home');
Route::get('/properties', [PublicController::class, 'properties'])->name('public.properties');
Route::get('/properties/{id}', [PublicController::class, 'propertyDetails'])->name('public.property_details');
Route::get('/about', [PublicController::class, 'about'])->name('public.about');
Route::get('/contact', [PublicController::class, 'contact'])->name('public.contact');
Route::get('/apply', [PublicController::class, 'apply'])->name('public.apply');
Route::post('/apply', [PublicController::class, 'applyStore'])->name('public.apply.store');
Route::get('/thank-you', [PublicController::class, 'thankYou'])->name('public.thank_you');
Route::view('/privacy-policy', 'public.privacy')->name('public.privacy');
Route::view('/terms-of-service', 'public.terms')->name('public.terms');
Route::get('/seo/health', function () {
    $sitemapUrl = url('/sitemap.xml');
    $robotsUrl = url('/robots.txt');
    $health = [
        'sitemap_url' => $sitemapUrl,
        'robots_url' => $robotsUrl,
        'sitemap_route_exists' => Route::has('seo.sitemap'),
        'robots_route_exists' => Route::has('seo.robots'),
        'public_routes' => [
            'home' => route('public.home'),
            'properties' => route('public.properties'),
            'about' => route('public.about'),
            'contact' => route('public.contact'),
        ],
    ];

    return view('seo.health', ['health' => $health]);
})->name('seo.health');

Route::get('/robots.txt', function () {
    $lines = [
        'User-agent: *',
        'Allow: /',
        'Disallow: /dashboard',
        'Disallow: /loan',
        'Disallow: /property',
        'Disallow: /superadmin',
        'Sitemap: '.url('/sitemap.xml'),
    ];

    return response(implode("\n", $lines)."\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('seo.robots');

Route::get('/sitemap.xml', function () {
    $viewLastMod = function (string $viewPath, string $fallback): string {
        $full = resource_path('views/'.str_replace('.', '/', $viewPath).'.blade.php');
        if (is_file($full)) {
            $mtime = @filemtime($full);
            if (is_int($mtime) && $mtime > 0) {
                return \Carbon\Carbon::createFromTimestamp($mtime)->toAtomString();
            }
        }
        return $fallback;
    };
    $now = now()->toAtomString();

    $urls = [
        ['loc' => url('/'), 'lastmod' => $viewLastMod('public.home', $now), 'changefreq' => 'daily', 'priority' => '1.0'],
        ['loc' => url('/properties'), 'lastmod' => $viewLastMod('public.properties', $now), 'changefreq' => 'daily', 'priority' => '0.9'],
        ['loc' => url('/about'), 'lastmod' => $viewLastMod('public.about', $now), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => url('/contact'), 'lastmod' => $viewLastMod('public.contact', $now), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => url('/privacy-policy'), 'lastmod' => $viewLastMod('public.privacy', $now), 'changefreq' => 'yearly', 'priority' => '0.4'],
        ['loc' => url('/terms-of-service'), 'lastmod' => $viewLastMod('public.terms', $now), 'changefreq' => 'yearly', 'priority' => '0.4'],
    ];

    if (class_exists(\App\Models\Property::class) && Schema::hasTable('properties')) {
        try {
            \App\Models\Property::query()
                ->select(['id', 'updated_at'])
                ->orderByDesc('updated_at')
                ->limit(500)
                ->get()
                ->each(function ($property) use (&$urls) {
                    $urls[] = [
                        'loc' => route('public.property_details', ['id' => $property->id]),
                        'lastmod' => optional($property->updated_at)->toAtomString() ?: now()->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                });
        } catch (\Throwable) {
            // Keep sitemap generation resilient even if property table/schema differs.
        }
    }

    $xml = view('seo.sitemap', ['urls' => $urls])->render();
    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('seo.sitemap');
Route::post('/webhooks/property/payments/stk-callback', [PropertyPaymentWebhookController::class, 'stkCallback'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.payments.stk_callback');
Route::post('/webhooks/property/payments/sms-ingest', [PropertyPaymentWebhookController::class, 'smsIngest'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.payments.sms_ingest');

// Safaricom Daraja STK callback (raw Daraja format)
Route::post('/webhooks/mpesa/stk-callback', [MpesaDarajaWebhookController::class, 'stkCallback'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.mpesa.stk_callback');

// Safaricom Daraja B2C Result URL callback
Route::post('/webhooks/mpesa/b2c-result', [MpesaDarajaWebhookController::class, 'b2cResultCallback'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.mpesa.b2c_result');
Route::post('/webhooks/property/payments/bank/{provider}', [PropertyPaymentWebhookController::class, 'bankCallback'])
    ->whereIn('provider', ['kcb', 'equity', 'coop'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.payments.bank_callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $system = request()->session()->get('active_system');
        if ($system === 'property') {
            $role = auth()->user()?->property_portal_role ?? 'agent';

            return match ($role) {
                'landlord' => redirect()->route('property.landlord.portfolio'),
                'tenant' => redirect()->route('property.tenant.home'),
                default => redirect()->route('property.dashboard'),
            };
        }

        return redirect()->route('loan.dashboard');
    })->name('dashboard');

    Route::get('/choose-module', [ChooseModuleController::class, 'show'])
        ->name('choose_module');

    Route::post('/choose-module/{module}', [ChooseModuleController::class, 'activate'])
        ->whereIn('module', ['property', 'loan'])
        ->name('choose_module.activate');

    Route::middleware('superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [SuperAdminConsoleController::class, 'dashboard'])->name('dashboard');
        Route::get('/access-approvals', [SuperAdminConsoleController::class, 'accessApprovals'])->name('access_approvals');
        Route::patch('/access-approvals/{access}', [SuperAdminConsoleController::class, 'updateAccessApproval'])->name('access_approvals.update');
        Route::post('/access-approvals/bulk', [SuperAdminConsoleController::class, 'bulkAccessApprovals'])->name('access_approvals.bulk');
        Route::get('/roles-permissions', [SuperAdminConsoleController::class, 'rolesPermissions'])->name('roles_permissions');
        Route::get('/agent-workspaces', [SuperAdminConsoleController::class, 'agentWorkspaces'])->name('agent_workspaces');
        Route::get('/audit-trail', [SuperAdminConsoleController::class, 'auditTrail'])->name('audit_trail');

        Route::get('/users', [SuperAdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [SuperAdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [SuperAdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [SuperAdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [SuperAdminUserController::class, 'update'])->name('users.update');
    });

    Route::middleware('module.access:loan')->group(function () {
        Route::get('/loan/dashboard', [LoanDashboardController::class, 'index'])->name('loan.dashboard');

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
        Route::post('/', [LoanClientsController::class, 'store'])->name('store');

        Route::get('/leads', [LoanClientsController::class, 'leads'])->name('leads');
        Route::get('/leads/create', [LoanClientsController::class, 'leadsCreate'])->name('leads.create');
        Route::post('/leads', [LoanClientsController::class, 'leadsStore'])->name('leads.store');
        Route::post('/leads/{loan_client}/convert', [LoanClientsController::class, 'leadsConvert'])->name('leads.convert');

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

        Route::get('/{loan_client}/interactions/create', [LoanClientsController::class, 'interactionCreateForClient'])->name('interactions.for_client.create');
        Route::post('/{loan_client}/interactions', [LoanClientsController::class, 'interactionStoreForClient'])->name('interactions.for_client.store');

        Route::get('/{loan_client}', [LoanClientsController::class, 'show'])->name('show');
        Route::get('/{loan_client}/edit', [LoanClientsController::class, 'edit'])->name('edit');
        Route::patch('/{loan_client}', [LoanClientsController::class, 'update'])->name('update');
        Route::delete('/{loan_client}', [LoanClientsController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('loan/employees')->name('loan.employees.')->group(function () {
        Route::get('/', [LoanEmployeesController::class, 'index'])->name('index');
        Route::get('/create', [LoanEmployeesController::class, 'create'])->name('create');
        Route::post('/', [LoanEmployeesController::class, 'store'])->name('store');

        Route::get('/leaves/create', [LoanEmployeesController::class, 'leavesCreate'])->name('leaves.create');
        Route::post('/leaves', [LoanEmployeesController::class, 'leavesStore'])->name('leaves.store');
        Route::patch('/leaves/{staff_leave}/status', [LoanEmployeesController::class, 'leavesUpdateStatus'])->name('leaves.status');
        Route::get('/leaves', [LoanEmployeesController::class, 'leaves'])->name('leaves');

        Route::get('/groups/create', [LoanEmployeesController::class, 'groupsCreate'])->name('groups.create');
        Route::post('/groups', [LoanEmployeesController::class, 'groupsStore'])->name('groups.store');
        Route::get('/groups', [LoanEmployeesController::class, 'groups'])->name('groups');
        Route::get('/groups/{staff_group}', [LoanEmployeesController::class, 'groupsShow'])->name('groups.show');
        Route::post('/groups/{staff_group}/members', [LoanEmployeesController::class, 'groupsMemberStore'])->name('groups.members.store');
        Route::delete('/groups/{staff_group}/members/{employee}', [LoanEmployeesController::class, 'groupsMemberDestroy'])->name('groups.members.destroy');
        Route::delete('/groups/{staff_group}', [LoanEmployeesController::class, 'groupsDestroy'])->name('groups.destroy');

        Route::get('/portfolios/create', [LoanEmployeesController::class, 'portfoliosCreate'])->name('portfolios.create');
        Route::post('/portfolios', [LoanEmployeesController::class, 'portfoliosStore'])->name('portfolios.store');
        Route::get('/portfolios', [LoanEmployeesController::class, 'portfolios'])->name('portfolios');
        Route::get('/portfolios/{staff_portfolio}/edit', [LoanEmployeesController::class, 'portfoliosEdit'])->name('portfolios.edit');
        Route::patch('/portfolios/{staff_portfolio}', [LoanEmployeesController::class, 'portfoliosUpdate'])->name('portfolios.update');
        Route::delete('/portfolios/{staff_portfolio}', [LoanEmployeesController::class, 'portfoliosDestroy'])->name('portfolios.destroy');

        Route::get('/loan-applications/create', [LoanEmployeesController::class, 'loanApplicationsCreate'])->name('loan_applications.create');
        Route::post('/loan-applications', [LoanEmployeesController::class, 'loanApplicationsStore'])->name('loan_applications.store');
        Route::get('/loan-applications', [LoanEmployeesController::class, 'loanApplications'])->name('loan_applications');
        Route::patch('/loan-applications/{staff_loan_application}', [LoanEmployeesController::class, 'loanApplicationsUpdate'])->name('loan_applications.update');

        Route::get('/staff-loans/create', [LoanEmployeesController::class, 'staffLoansCreate'])->name('staff_loans.create');
        Route::post('/staff-loans', [LoanEmployeesController::class, 'staffLoansStore'])->name('staff_loans.store');
        Route::get('/staff-loans', [LoanEmployeesController::class, 'staffLoans'])->name('staff_loans');
        Route::get('/staff-loans/{staff_loan}/edit', [LoanEmployeesController::class, 'staffLoansEdit'])->name('staff_loans.edit');
        Route::patch('/staff-loans/{staff_loan}', [LoanEmployeesController::class, 'staffLoansUpdate'])->name('staff_loans.update');
        Route::delete('/staff-loans/{staff_loan}', [LoanEmployeesController::class, 'staffLoansDestroy'])->name('staff_loans.destroy');

        Route::post('/workplan/items', [LoanEmployeesController::class, 'workplanItemStore'])->name('workplan.items.store');
        Route::post('/workplan/items/{workplan_item}/toggle', [LoanEmployeesController::class, 'workplanItemToggle'])->name('workplan.items.toggle');
        Route::delete('/workplan/items/{workplan_item}', [LoanEmployeesController::class, 'workplanItemDestroy'])->name('workplan.items.destroy');
        Route::get('/workplan', [LoanEmployeesController::class, 'workplan'])->name('workplan');

        Route::get('/{employee}/edit', [LoanEmployeesController::class, 'edit'])->name('edit');
        Route::patch('/{employee}', [LoanEmployeesController::class, 'update'])->name('update');
        Route::delete('/{employee}', [LoanEmployeesController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('loan/regions')->name('loan.regions.')->group(function () {
        Route::get('/create', [LoanOrganizationController::class, 'regionsCreate'])->name('create');
        Route::post('/', [LoanOrganizationController::class, 'regionsStore'])->name('store');
        Route::get('/', [LoanOrganizationController::class, 'regionsIndex'])->name('index');
        Route::get('/{loan_region}/edit', [LoanOrganizationController::class, 'regionsEdit'])->name('edit');
        Route::patch('/{loan_region}', [LoanOrganizationController::class, 'regionsUpdate'])->name('update');
        Route::delete('/{loan_region}', [LoanOrganizationController::class, 'regionsDestroy'])->name('destroy');
    });

    Route::prefix('loan/branches')->name('loan.branches.')->group(function () {
        Route::get('/loan-summary', [LoanOrganizationController::class, 'branchLoanSummary'])->name('loan_summary');
        Route::get('/create', [LoanOrganizationController::class, 'branchesCreate'])->name('create');
        Route::post('/', [LoanOrganizationController::class, 'branchesStore'])->name('store');
        Route::get('/', [LoanOrganizationController::class, 'branchesIndex'])->name('index');
        Route::get('/{loan_branch}/edit', [LoanOrganizationController::class, 'branchesEdit'])->name('edit');
        Route::patch('/{loan_branch}', [LoanOrganizationController::class, 'branchesUpdate'])->name('update');
        Route::delete('/{loan_branch}', [LoanOrganizationController::class, 'branchesDestroy'])->name('destroy');
    });

    Route::prefix('loan/analytics')->name('loan.analytics.')->group(function () {
        Route::get('/loan-sizes/create', [LoanBusinessAnalyticsController::class, 'loanSizesCreate'])->name('loan_sizes.create');
        Route::post('/loan-sizes', [LoanBusinessAnalyticsController::class, 'loanSizesStore'])->name('loan_sizes.store');
        Route::get('/loan-sizes', [LoanBusinessAnalyticsController::class, 'loanSizesIndex'])->name('loan_sizes');
        Route::get('/loan-sizes/{analytics_loan_size}/edit', [LoanBusinessAnalyticsController::class, 'loanSizesEdit'])->name('loan_sizes.edit');
        Route::patch('/loan-sizes/{analytics_loan_size}', [LoanBusinessAnalyticsController::class, 'loanSizesUpdate'])->name('loan_sizes.update');
        Route::delete('/loan-sizes/{analytics_loan_size}', [LoanBusinessAnalyticsController::class, 'loanSizesDestroy'])->name('loan_sizes.destroy');

        Route::get('/targets/create', [LoanBusinessAnalyticsController::class, 'targetsCreate'])->name('targets.create');
        Route::post('/targets', [LoanBusinessAnalyticsController::class, 'targetsStore'])->name('targets.store');
        Route::get('/targets', [LoanBusinessAnalyticsController::class, 'targetsIndex'])->name('targets');
        Route::get('/targets/{analytics_period_target}/edit', [LoanBusinessAnalyticsController::class, 'targetsEdit'])->name('targets.edit');
        Route::patch('/targets/{analytics_period_target}', [LoanBusinessAnalyticsController::class, 'targetsUpdate'])->name('targets.update');
        Route::delete('/targets/{analytics_period_target}', [LoanBusinessAnalyticsController::class, 'targetsDestroy'])->name('targets.destroy');

        Route::get('/performance/create', [LoanBusinessAnalyticsController::class, 'performanceCreate'])->name('performance.create');
        Route::post('/performance', [LoanBusinessAnalyticsController::class, 'performanceStore'])->name('performance.store');
        Route::get('/performance', [LoanBusinessAnalyticsController::class, 'performanceIndex'])->name('performance');
        Route::get('/performance/{analytics_performance_record}/edit', [LoanBusinessAnalyticsController::class, 'performanceEdit'])->name('performance.edit');
        Route::patch('/performance/{analytics_performance_record}', [LoanBusinessAnalyticsController::class, 'performanceUpdate'])->name('performance.update');
        Route::delete('/performance/{analytics_performance_record}', [LoanBusinessAnalyticsController::class, 'performanceDestroy'])->name('performance.destroy');
    });

    Route::prefix('loan/accounting')->middleware('loan.role:accountant,admin,manager')->name('loan.accounting.')->group(function () {
        Route::get('/expense-summary', [LoanAccountingController::class, 'expenseSummary'])->name('expense_summary');
        Route::get('/cashflow', [LoanAccountingController::class, 'cashflow'])->name('cashflow');

        Route::get('/books', [LoanAccountingController::class, 'books'])->name('books');
        Route::get('/books/chart-rules', [LoanAccountingBooksController::class, 'chartRules'])->name('books.chart_rules');

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
        Route::post('/chart-of-accounts/wallet-slots', [LoanAccountingController::class, 'chartWalletSlotsUpdate'])->name('chart.wallet_slots.update');
        Route::patch('/chart-of-accounts/posting-rules/{accounting_posting_rule}', [LoanAccountingController::class, 'chartPostingRuleUpdate'])->name('chart.posting_rules.update');
        Route::get('/chart-of-accounts', [LoanAccountingController::class, 'chartIndex'])->name('chart.index');
        Route::get('/chart-of-accounts/{accounting_chart_account}/edit', [LoanAccountingController::class, 'chartEdit'])->name('chart.edit');
        Route::patch('/chart-of-accounts/{accounting_chart_account}', [LoanAccountingController::class, 'chartUpdate'])->name('chart.update');
        Route::delete('/chart-of-accounts/{accounting_chart_account}', [LoanAccountingController::class, 'chartDestroy'])->name('chart.destroy');

        Route::get('/journal-entries/create', [LoanAccountingController::class, 'journalCreate'])->name('journal.create');
        Route::post('/journal-entries', [LoanAccountingController::class, 'journalStore'])->name('journal.store');
        Route::get('/journal-entries', [LoanAccountingController::class, 'journalIndex'])->name('journal.index');
        Route::post('/journal-entries/bulk', [LoanAccountingController::class, 'journalBulk'])->name('journal.bulk');
        Route::get('/journal-entries/{accounting_journal_entry}', [LoanAccountingController::class, 'journalShow'])->name('journal.show');
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
        Route::get('/app-loans-report', [LoanBookApplicationsController::class, 'report'])->name('app_loans_report');
        Route::get('/applications/create', [LoanBookApplicationsController::class, 'create'])->name('applications.create');
        Route::post('/applications', [LoanBookApplicationsController::class, 'store'])->name('applications.store');
        Route::get('/applications', [LoanBookApplicationsController::class, 'index'])->name('applications.index');
        Route::get('/applications/{loan_book_application}/edit', [LoanBookApplicationsController::class, 'edit'])->name('applications.edit');
        Route::patch('/applications/{loan_book_application}', [LoanBookApplicationsController::class, 'update'])->name('applications.update');
        Route::delete('/applications/{loan_book_application}', [LoanBookApplicationsController::class, 'destroy'])->name('applications.destroy');

        Route::get('/loans/create', [LoanBookLoansController::class, 'create'])->name('loans.create');
        Route::post('/loans', [LoanBookLoansController::class, 'store'])->name('loans.store');
        Route::get('/loans', [LoanBookLoansController::class, 'index'])->name('loans.index');
        Route::get('/loan-arrears', [LoanBookLoansController::class, 'arrears'])->name('loan_arrears');
        Route::get('/checkoff-loans', [LoanBookLoansController::class, 'checkoff'])->name('checkoff_loans');
        Route::get('/loans/{loan_book_loan}/edit', [LoanBookLoansController::class, 'edit'])->name('loans.edit');
        Route::patch('/loans/{loan_book_loan}', [LoanBookLoansController::class, 'update'])->name('loans.update');
        Route::delete('/loans/{loan_book_loan}', [LoanBookLoansController::class, 'destroy'])->name('loans.destroy');

        Route::get('/disbursements/create', [LoanBookOperationsController::class, 'disbursementsCreate'])->name('disbursements.create');
        Route::post('/disbursements', [LoanBookOperationsController::class, 'disbursementsStore'])->name('disbursements.store');
        Route::get('/disbursements', [LoanBookOperationsController::class, 'disbursementsIndex'])->name('disbursements.index');
        Route::delete('/disbursements/{loan_book_disbursement}', [LoanBookOperationsController::class, 'disbursementsDestroy'])->name('disbursements.destroy');

        Route::get('/collection-sheet', [LoanBookOperationsController::class, 'collectionSheet'])->name('collection_sheet.index');
        Route::post('/collection-sheet', [LoanBookOperationsController::class, 'collectionSheetStore'])->name('collection_sheet.store');
        Route::delete('/collection-sheet/{loan_book_collection_entry}', [LoanBookOperationsController::class, 'collectionSheetDestroy'])->name('collection_sheet.destroy');

        Route::get('/collection-mtd', [LoanBookOperationsController::class, 'collectionMtd'])->name('collection_mtd');
        Route::get('/collection-reports', [LoanBookOperationsController::class, 'collectionReports'])->name('collection_reports');

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
        Route::get('/processed', [LoanPaymentsController::class, 'processed'])->name('processed');
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
        Route::get('/merge', [LoanPaymentsController::class, 'mergeForm'])->name('merge');
        Route::post('/merge', [LoanPaymentsController::class, 'mergeStore'])->name('merge.store');
        Route::get('/reversal/create', [LoanPaymentsController::class, 'reversalCreate'])->name('reversal.create');
        Route::post('/reversal', [LoanPaymentsController::class, 'reversalStore'])->name('reversal.store');
        Route::get('/create', [LoanPaymentsController::class, 'create'])->name('create');
        Route::post('/', [LoanPaymentsController::class, 'store'])->name('store');
        Route::post('/{loan_book_payment}/post', [LoanPaymentsController::class, 'post'])->name('post');
        Route::get('/{loan_book_payment}/edit', [LoanPaymentsController::class, 'edit'])->name('edit');
        Route::patch('/{loan_book_payment}', [LoanPaymentsController::class, 'update'])->name('update');
        Route::delete('/{loan_book_payment}', [LoanPaymentsController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('loan/bulk-sms')->name('loan.bulksms.')->group(function () {
        Route::get('/compose', [LoanBulkSmsController::class, 'compose'])->name('compose');
        Route::post('/compose', [LoanBulkSmsController::class, 'composeStore'])->name('compose.store');

        Route::get('/templates/create', [LoanBulkSmsController::class, 'templatesCreate'])->name('templates.create');
        Route::post('/templates', [LoanBulkSmsController::class, 'templatesStore'])->name('templates.store');
        Route::get('/templates', [LoanBulkSmsController::class, 'templatesIndex'])->name('templates.index');
        Route::get('/templates/{sms_template}/edit', [LoanBulkSmsController::class, 'templatesEdit'])->name('templates.edit');
        Route::patch('/templates/{sms_template}', [LoanBulkSmsController::class, 'templatesUpdate'])->name('templates.update');
        Route::delete('/templates/{sms_template}', [LoanBulkSmsController::class, 'templatesDestroy'])->name('templates.destroy');

        Route::get('/logs', [LoanBulkSmsController::class, 'logs'])->name('logs');
        Route::get('/wallet', [LoanBulkSmsController::class, 'wallet'])->name('wallet');
        Route::post('/wallet/topup', [LoanBulkSmsController::class, 'walletTopup'])->name('wallet.topup');
        Route::get('/schedules', [LoanBulkSmsController::class, 'schedules'])->name('schedules');
        Route::post('/schedules/{sms_schedule}/cancel', [LoanBulkSmsController::class, 'schedulesCancel'])->name('schedules.cancel');
    });

    Route::prefix('loan/system-help')->name('loan.system.')->group(function () {
        Route::get('/tickets/create', [LoanSystemHelpController::class, 'ticketsCreate'])->name('tickets.create');
        Route::post('/tickets', [LoanSystemHelpController::class, 'ticketsStore'])->name('tickets.store');
        Route::get('/tickets', [LoanSystemHelpController::class, 'ticketsIndex'])->name('tickets.index');
        Route::get('/tickets/{loan_support_ticket}', [LoanSystemHelpController::class, 'ticketsShow'])->name('tickets.show');
        Route::get('/tickets/{loan_support_ticket}/edit', [LoanSystemHelpController::class, 'ticketsEdit'])->name('tickets.edit');
        Route::patch('/tickets/{loan_support_ticket}', [LoanSystemHelpController::class, 'ticketsUpdate'])->name('tickets.update');
        Route::delete('/tickets/{loan_support_ticket}', [LoanSystemHelpController::class, 'ticketsDestroy'])->name('tickets.destroy');
        Route::post('/tickets/{loan_support_ticket}/replies', [LoanSystemHelpController::class, 'ticketsReplyStore'])->name('tickets.replies.store');
        Route::patch('/tickets/{loan_support_ticket}/status', [LoanSystemHelpController::class, 'ticketsStatusUpdate'])->name('tickets.status');

        Route::get('/setup', [LoanSystemHelpController::class, 'setupHub'])->name('setup');
        Route::get('/setup/company', [LoanSystemHelpController::class, 'setupCompany'])->name('setup.company');
        Route::post('/setup/company', [LoanSystemHelpController::class, 'setupCompanyUpdate'])->name('setup.company.update');
        Route::get('/setup/preferences', [LoanSystemHelpController::class, 'setupPreferences'])->name('setup.preferences');
        Route::post('/setup/preferences', [LoanSystemHelpController::class, 'setupPreferencesUpdate'])->name('setup.preferences.update');

        Route::get('/setup/loan-form/client', [LoanFormSetupController::class, 'clientForm'])->name('form_setup.client');
        Route::post('/setup/loan-form/client', [LoanFormSetupController::class, 'clientFormSave'])->name('form_setup.client.save');
        Route::get('/setup/loan-form/staff', [LoanFormSetupController::class, 'staffForm'])->name('form_setup.staff');
        Route::post('/setup/loan-form/staff', [LoanFormSetupController::class, 'staffFormSave'])->name('form_setup.staff.save');

        Route::get('/setup/salary-advance-form', [LoanFormSetupController::class, 'salaryAdvanceForm'])->name('form_setup.salary_advance');
        Route::post('/setup/salary-advance-form', [LoanFormSetupController::class, 'salaryAdvanceFormSave'])->name('form_setup.salary_advance.save');

        Route::get('/setup/forms/{page}', [LoanFormSetupController::class, 'setupPage'])
            ->where('page', LoanFormSetupController::FORM_SETUP_PAGE_PATTERN)
            ->name('form_setup.page');
        Route::post('/setup/forms/{page}', [LoanFormSetupController::class, 'setupPageSave'])
            ->where('page', LoanFormSetupController::FORM_SETUP_PAGE_PATTERN)
            ->name('form_setup.page.save');

        Route::get('/access-logs', [LoanSystemHelpController::class, 'accessLogsIndex'])->name('access_logs.index');
    });
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
