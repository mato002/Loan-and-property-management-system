<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Models\LoanBranch;
use App\Models\LoanClient;
use App\Models\LoanProduct;
use App\Models\PmInvoice;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanBookApplicationsController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function index(Request $request)
    {
        $query = LoanBookApplication::query()->with(['loanClient.assignedEmployee', 'loan']);
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $q = trim((string) $request->query('q', ''));
        $stage = trim((string) $request->query('stage', ''));
        $branch = trim((string) $request->query('branch', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 15)));

        $query
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('product_name', 'like', '%'.$q.'%')
                        ->orWhere('branch', 'like', '%'.$q.'%')
                        ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                            $client->where('client_number', 'like', '%'.$q.'%')
                                ->orWhere('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%')
                                ->orWhere('email', 'like', '%'.$q.'%')
                                ->orWhere('phone', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($stage !== '', fn (Builder $builder) => $builder->where('stage', $stage))
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch));

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('created_at')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-applications-'.now()->format('Ymd_His'),
                ['Reference', 'Client #', 'Client Name', 'Product', 'Source', 'Amount', 'Term Months', 'Stage', 'Branch', 'Submitted'],
                function () use ($rows) {
                    foreach ($rows as $app) {
                        yield [
                            (string) $app->reference,
                            (string) ($app->loanClient?->client_number ?? ''),
                            (string) ($app->loanClient?->full_name ?? ''),
                            (string) $app->product_name,
                            (string) ($app->submission_source ?? ''),
                            number_format((float) $app->amount_requested, 2, '.', ''),
                            (string) $app->term_months,
                            (string) $app->stage,
                            (string) ($app->branch ?? ''),
                            (string) optional($app->submitted_at)->format('Y-m-d'),
                        ];
                    }
                },
                $export
            );
        }

        $applications = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        $branches = LoanBookApplication::query()
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch');

        return view('loan.book.applications.index', [
            'title' => 'Loan applications',
            'subtitle' => 'Customer LoanBook pipeline — from submission to disbursement.',
            'applications' => $applications,
            'q' => $q,
            'stage' => $stage,
            'branch' => $branch,
            'perPage' => $perPage,
            'stages' => $this->stageOptions(),
            'branches' => $branches,
            'productMetaByName' => $this->productMetaByName(),
        ]);
    }

    public function report(Request $request)
    {
        $query = LoanBookApplication::query()->with(['loanClient', 'loan']);
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $q = trim((string) $request->query('q', ''));
        $stage = trim((string) $request->query('stage', ''));
        $branch = trim((string) $request->query('branch', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));

        $query
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('product_name', 'like', '%'.$q.'%')
                        ->orWhere('branch', 'like', '%'.$q.'%')
                        ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                            $client->where('client_number', 'like', '%'.$q.'%')
                                ->orWhere('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%')
                                ->orWhere('email', 'like', '%'.$q.'%')
                                ->orWhere('phone', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($stage !== '', fn (Builder $builder) => $builder->where('stage', $stage))
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch));

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('created_at')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-application-report-'.now()->format('Ymd_His'),
                ['Reference', 'Client #', 'Client Name', 'Product', 'Source', 'Amount', 'Term Months', 'Stage', 'Branch', 'Submitted'],
                function () use ($rows) {
                    foreach ($rows as $app) {
                        yield [
                            (string) $app->reference,
                            (string) ($app->loanClient?->client_number ?? ''),
                            (string) ($app->loanClient?->full_name ?? ''),
                            (string) $app->product_name,
                            (string) ($app->submission_source ?? ''),
                            number_format((float) $app->amount_requested, 2, '.', ''),
                            (string) $app->term_months,
                            (string) $app->stage,
                            (string) ($app->branch ?? ''),
                            (string) optional($app->submitted_at)->format('Y-m-d'),
                        ];
                    }
                },
                $export
            );
        }

        $applications = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        $branches = LoanBookApplication::query()
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch');

        return view('loan.book.applications.report', [
            'title' => 'Application loans report',
            'subtitle' => 'Export-style listing for committee and MIS.',
            'applications' => $applications,
            'q' => $q,
            'stage' => $stage,
            'branch' => $branch,
            'perPage' => $perPage,
            'stages' => $this->stageOptions(),
            'branches' => $branches,
        ]);
    }

    public function create(Request $request): View
    {
        if ($this->tenantHasPropertyArrears($request)) {
            abort(403, 'Clear property arrears before creating a loan application.');
        }

        $selectedClientId = null;
        if ((string) $request->query('prefill') === 'portal') {
            $selectedClientId = $this->resolvePortalClientId($request);
        }
        $portalRole = strtolower(trim((string) $request->query('portal_role', '')));
        $defaultProductName = match ($portalRole) {
            'tenant' => 'Tenant personal loan',
            'landlord' => 'Landlord property improvement loan',
            default => '',
        };
        $defaultPurpose = match ($portalRole) {
            'tenant' => 'Personal or household financing request via tenant portal.',
            'landlord' => 'Property-related financing request via landlord portal.',
            default => '',
        };

        $prefillClientId = $selectedClientId ? (int) $selectedClientId : null;
        $clientsQuery = LoanClient::query()
            ->clients()
            ->when(! $this->canAccessAllLoanData($request->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId($request->user())))
            ->when(
                $prefillClientId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($prefillClientId): void {
                    $inner->whereDoesntHave('loanBookLoans', function (Builder $loan): void {
                        $loan->where('status', '!=', LoanBookLoan::STATUS_CLOSED);
                    })->orWhere('id', $prefillClientId);
                }),
                fn (Builder $query) => $query->whereDoesntHave('loanBookLoans', function (Builder $loan): void {
                    $loan->where('status', '!=', LoanBookLoan::STATUS_CLOSED);
                }),
            )
            ->orderBy('last_name')
            ->orderBy('first_name');

        return view('loan.book.applications.create', [
            'title' => 'Create application',
            'subtitle' => 'Start a new LoanBook file for an onboarded client.',
            'clients' => $clientsQuery->get(),
            'stages' => $this->stageOptions(),
            'selectedClientId' => $selectedClientId,
            'defaultProductName' => $defaultProductName,
            'defaultPurpose' => $defaultPurpose,
            'productOptions' => $this->productOptions($defaultProductName),
            'productMetaByName' => $this->productMetaByName(),
            'branchOptions' => $this->branchOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->tenantHasPropertyArrears($request)) {
            return redirect()
                ->route('property.tenant.loans')
                ->withErrors([
                    'loan' => 'Clear your rent arrears first before applying for a loan.',
                ]);
        }

        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'term_value' => ['required', 'integer', 'min:1', 'max:3660'],
            'term_unit' => ['required', 'string', 'in:'.implode(',', $this->termUnitOptions())],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'interest_rate_period' => ['nullable', 'string', 'in:'.implode(',', $this->interestRatePeriodOptions())],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'stage' => ['required', 'string', 'in:'.implode(',', array_keys($this->stageOptions()))],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'applicant_pin_location_code' => ['nullable', 'string', 'max:120'],
            'applicant_signature_name' => ['nullable', 'string', 'max:200'],
            'guarantor_full_name' => ['nullable', 'string', 'max:200'],
            'guarantor_id_number' => ['nullable', 'string', 'max:80'],
            'guarantor_phone' => ['nullable', 'string', 'max:40'],
            'guarantor_signature_name' => ['nullable', 'string', 'max:200'],
        ]);
        $validated['submission_source'] = 'manual_internal';
        $validated['repayment_agreement_accepted'] = $request->boolean('repayment_agreement_accepted');
        $termValue = (int) ($validated['term_value'] ?? 0);
        $termUnit = (string) ($validated['term_unit'] ?? 'monthly');
        if ($termValue <= 0) {
            $termValue = max(1, (int) ($validated['term_months'] ?? 1));
            $termUnit = 'monthly';
        }
        $validated['term_value'] = $termValue;
        $validated['term_unit'] = $termUnit;
        $validated['term_months'] = $this->scheduleToMonths($termValue, $termUnit);
        $validated['interest_rate_period'] = $validated['interest_rate_period'] ?? 'annual';

        if (LoanBookLoan::query()
            ->where('loan_client_id', $validated['loan_client_id'])
            ->where('status', '!=', LoanBookLoan::STATUS_CLOSED)
            ->exists()) {
            return redirect()
                ->back()
                ->withErrors([
                    'loan_client_id' => __('This client already has an open loan. Use another client or close the existing loan first.'),
                ])
                ->withInput();
        }

        $client = LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $this->mergeLoanDepartmentGuarantorFromClient($validated, $client);
        if (empty($validated['branch'])) {
            $validated['branch'] = $client->branch;
        }
        $validated['product_name'] = $this->ensureProductRegistered((string) $validated['product_name']);

        $next = (LoanBookApplication::query()->max('id') ?? 0) + 1;
        $validated['reference'] = 'APP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $validated['submitted_at'] = now();

        LoanBookApplication::query()->create($validated);

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application saved.'));
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_term_unit' => ['nullable', 'string', 'in:'.implode(',', $this->termUnitOptions())],
            'default_interest_rate_period' => ['nullable', 'string', 'in:'.implode(',', $this->interestRatePeriodOptions())],
        ]);

        $name = trim((string) $validated['name']);
        $description = trim((string) ($validated['description'] ?? ''));
        $hasDefaultTermUnit = Schema::hasColumn('loan_products', 'default_term_unit');
        $hasDefaultRatePeriod = Schema::hasColumn('loan_products', 'default_interest_rate_period');

        $createPayload = [
            'description' => $description !== '' ? $description : null,
            'default_interest_rate' => isset($validated['default_interest_rate']) && $validated['default_interest_rate'] !== ''
                ? (float) $validated['default_interest_rate']
                : null,
            'default_term_months' => isset($validated['default_term_months']) && $validated['default_term_months'] !== ''
                ? (int) $validated['default_term_months']
                : null,
            'is_active' => true,
        ];
        if ($hasDefaultTermUnit) {
            $createPayload['default_term_unit'] = (string) ($validated['default_term_unit'] ?? 'monthly');
        }
        if ($hasDefaultRatePeriod) {
            $createPayload['default_interest_rate_period'] = (string) ($validated['default_interest_rate_period'] ?? 'annual');
        }

        $product = LoanProduct::query()->firstOrCreate(
            ['name' => $name],
            $createPayload
        );

        return response()->json([
            'ok' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'default_interest_rate' => $product->default_interest_rate,
                'default_term_months' => $product->default_term_months,
                'default_term_unit' => $hasDefaultTermUnit ? ($product->default_term_unit ?? 'monthly') : 'monthly',
                'default_interest_rate_period' => $hasDefaultRatePeriod ? ($product->default_interest_rate_period ?? 'annual') : 'annual',
                'charges_summary' => '',
            ],
        ]);
    }

    public function edit(LoanBookApplication $loan_book_application): View
    {
        $this->ensureLoanClientOwner($loan_book_application->loanClient);

        $clients = LoanClient::query()
            ->clients()
            ->when(! $this->canAccessAllLoanData(auth()->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId(auth()->user())))
            ->where(function (Builder $query) use ($loan_book_application): void {
                $query->whereDoesntHave('loanBookLoans', function (Builder $loan): void {
                    $loan->where('status', '!=', LoanBookLoan::STATUS_CLOSED);
                })->orWhere('id', $loan_book_application->loan_client_id);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.book.applications.edit', [
            'title' => 'Edit application',
            'subtitle' => $loan_book_application->reference,
            'application' => $loan_book_application,
            'clients' => $clients,
            'stages' => $this->stageOptions(),
            'productOptions' => $this->productOptions($loan_book_application->product_name),
            'productMetaByName' => $this->productMetaByName(),
            'branchOptions' => $this->branchOptions(),
        ]);
    }

    public function show(LoanBookApplication $loan_book_application): View
    {
        $loan_book_application->load([
            'loanClient.assignedEmployee',
            'loan',
        ]);
        $this->ensureLoanClientOwner($loan_book_application->loanClient);

        return view('loan.book.applications.show', [
            'title' => 'Application details',
            'subtitle' => $loan_book_application->reference,
            'application' => $loan_book_application,
            'productMetaByName' => $this->productMetaByName(),
        ]);
    }

    public function update(Request $request, LoanBookApplication $loan_book_application): RedirectResponse
    {
        $this->ensureLoanClientOwner($loan_book_application->loanClient);

        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'term_value' => ['required', 'integer', 'min:1', 'max:3660'],
            'term_unit' => ['required', 'string', 'in:'.implode(',', $this->termUnitOptions())],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'interest_rate_period' => ['nullable', 'string', 'in:'.implode(',', $this->interestRatePeriodOptions())],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'stage' => ['required', 'string', 'in:'.implode(',', array_keys($this->stageOptions()))],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'applicant_pin_location_code' => ['nullable', 'string', 'max:120'],
            'applicant_signature_name' => ['nullable', 'string', 'max:200'],
            'guarantor_full_name' => ['nullable', 'string', 'max:200'],
            'guarantor_id_number' => ['nullable', 'string', 'max:80'],
            'guarantor_phone' => ['nullable', 'string', 'max:40'],
            'guarantor_signature_name' => ['nullable', 'string', 'max:200'],
        ]);
        if (empty($loan_book_application->submission_source)) {
            $validated['submission_source'] = 'manual_internal';
        }
        $validated['repayment_agreement_accepted'] = $request->boolean('repayment_agreement_accepted');
        $termValue = (int) ($validated['term_value'] ?? 0);
        $termUnit = (string) ($validated['term_unit'] ?? 'monthly');
        if ($termValue <= 0) {
            $termValue = max(1, (int) ($validated['term_months'] ?? $loan_book_application->term_months ?? 1));
            $termUnit = 'monthly';
        }
        $validated['term_value'] = $termValue;
        $validated['term_unit'] = $termUnit;
        $validated['term_months'] = $this->scheduleToMonths($termValue, $termUnit);
        $validated['interest_rate_period'] = $validated['interest_rate_period'] ?? 'annual';
        $validated['product_name'] = $this->ensureProductRegistered((string) $validated['product_name']);
        if (isset($validated['stage'])) {
            $stageError = $this->stageTransitionError($loan_book_application, (string) $validated['stage']);
            if ($stageError !== null) {
                return redirect()
                    ->back()
                    ->withErrors(['stage' => $stageError])
                    ->withInput();
            }
        }

        if ((int) $validated['loan_client_id'] !== (int) $loan_book_application->loan_client_id) {
            if (LoanBookLoan::query()
                ->where('loan_client_id', $validated['loan_client_id'])
                ->where('status', '!=', LoanBookLoan::STATUS_CLOSED)
                ->exists()) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'loan_client_id' => __('That client already has an open loan. Pick a different client.'),
                    ])
                    ->withInput();
            }
        }

        $client = LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $this->mergeLoanDepartmentGuarantorFromClient($validated, $client);
        $loan_book_application->update($validated);

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application updated.'));
    }

    public function destroy(LoanBookApplication $loan_book_application): RedirectResponse
    {
        $this->ensureLoanClientOwner($loan_book_application->loanClient);

        if ($loan_book_application->loan()->exists()) {
            return redirect()
                ->route('loan.book.applications.index')
                ->with('error', __('Cannot delete an application that already has a loan record.'));
        }

        $loan_book_application->delete();

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application removed.'));
    }

    /**
     * Update pipeline stage from the applications list without opening the full edit form.
     */
    public function updateStage(Request $request, LoanBookApplication $loan_book_application): RedirectResponse
    {
        $this->ensureLoanClientOwner($loan_book_application->loanClient);

        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:'.implode(',', array_keys($this->stageOptions()))],
        ]);
        $stageError = $this->stageTransitionError($loan_book_application, (string) $validated['stage']);
        if ($stageError !== null) {
            return redirect()
                ->back()
                ->withErrors(['stage' => $stageError]);
        }

        $loan_book_application->update(['stage' => $validated['stage']]);

        return redirect()
            ->back()
            ->with('status', __('Stage updated.'));
    }

    /**
     * If guarantor lines are left blank on the application, copy the primary guarantor from the client record.
     *
     * @param  array<string, mixed>  $validated
     */
    private function mergeLoanDepartmentGuarantorFromClient(array &$validated, LoanClient $client): void
    {
        $map = [
            'guarantor_full_name' => 'guarantor_1_full_name',
            'guarantor_id_number' => 'guarantor_1_id_number',
            'guarantor_phone' => 'guarantor_1_phone',
        ];
        foreach ($map as $appField => $clientField) {
            if (blank($validated[$appField] ?? null) && filled($client->{$clientField} ?? null)) {
                $validated[$appField] = $client->{$clientField};
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function stageOptions(): array
    {
        return [
            LoanBookApplication::STAGE_SUBMITTED => 'Submitted',
            LoanBookApplication::STAGE_CREDIT_REVIEW => 'Credit review',
            LoanBookApplication::STAGE_APPROVED => 'Approved',
            LoanBookApplication::STAGE_DECLINED => 'Declined',
            LoanBookApplication::STAGE_DISBURSED => 'Disbursed',
        ];
    }

    /**
     * @return list<string>
     */
    private function productOptions(?string $defaultProductName = null): array
    {
        $saved = Schema::hasTable('loan_products')
            ? LoanProduct::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all()
            : [];

        $historic = LoanBookApplication::query()
            ->select('product_name')
            ->whereNotNull('product_name')
            ->where('product_name', '!=', '')
            ->distinct()
            ->orderBy('product_name')
            ->pluck('product_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        $merged = array_values(array_unique(array_merge($saved, $historic)));
        sort($merged, SORT_NATURAL | SORT_FLAG_CASE);

        $default = trim((string) ($defaultProductName ?? ''));
        if ($default !== '' && ! in_array($default, $merged, true)) {
            $merged[] = $default;
            sort($merged, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $merged;
    }

    private function ensureProductRegistered(string $productName): string
    {
        $name = trim($productName);

        if ($name === '') {
            return $name;
        }

        if (Schema::hasTable('loan_products')) {
            LoanProduct::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }

        return $name;
    }

    /**
     * @return array<string, array{
     *     default_interest_rate: ?float,
     *     default_term_months: ?int,
     *     default_term_unit: string,
 *     default_interest_rate_period: string,
 *     charges_summary: string
     * }>
     */
    private function productMetaByName(): array
    {
        if (! Schema::hasTable('loan_products')) {
            return [];
        }

        $hasDefaultTermUnit = Schema::hasColumn('loan_products', 'default_term_unit');
        $hasDefaultRatePeriod = Schema::hasColumn('loan_products', 'default_interest_rate_period');
        $select = ['name', 'default_interest_rate', 'default_term_months'];
        if ($hasDefaultTermUnit) {
            $select[] = 'default_term_unit';
        }
        if ($hasDefaultRatePeriod) {
            $select[] = 'default_interest_rate_period';
        }
        $hasCharges = Schema::hasTable('loan_product_charges');

        return LoanProduct::query()
            ->with($hasCharges ? ['charges' => fn ($q) => $q->where('is_active', true)->orderBy('id')] : [])
            ->select($select)
            ->get()
            ->mapWithKeys(fn (LoanProduct $product) => [
                trim((string) $product->name) => [
                    'default_interest_rate' => $product->default_interest_rate !== null ? (float) $product->default_interest_rate : null,
                    'default_term_months' => $product->default_term_months !== null ? (int) $product->default_term_months : null,
                    'default_term_unit' => $hasDefaultTermUnit ? (string) ($product->default_term_unit ?? 'monthly') : 'monthly',
                    'default_interest_rate_period' => $hasDefaultRatePeriod ? (string) ($product->default_interest_rate_period ?? 'annual') : 'annual',
                    'charges_summary' => $hasCharges
                        ? $product->charges->map(function ($charge): string {
                            $amount = (string) $charge->amount_type === 'percent'
                                ? number_format((float) $charge->amount, 4).'%'
                                : number_format((float) $charge->amount, 2);

                            return trim((string) $charge->charge_name).' '.$amount.' ('.str_replace('_', ' ', (string) $charge->applies_to_stage).')';
                        })->implode('; ')
                        : '',
                ],
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function termUnitOptions(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    /**
     * @return list<string>
     */
    private function interestRatePeriodOptions(): array
    {
        return ['daily', 'weekly', 'monthly', 'annual'];
    }

    private function scheduleToMonths(int $termValue, string $termUnit): int
    {
        $value = max(1, $termValue);
        $unit = strtolower(trim($termUnit));

        return match ($unit) {
            'daily' => max(1, (int) ceil($value / 30)),
            'weekly' => max(1, (int) ceil($value / 4)),
            default => min(600, $value),
        };
    }

    /**
     * @return list<string>
     */
    private function branchOptions(): array
    {
        $fromDirectory = Schema::hasTable('loan_branches')
            ? LoanBranch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->all()
            : [];

        $fromClients = LoanClient::query()
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch')
            ->all();

        $fromApplications = LoanBookApplication::query()
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch')
            ->all();

        $options = array_values(array_unique(array_filter(array_map(
            static fn ($name) => trim((string) $name),
            array_merge($fromDirectory, $fromClients, $fromApplications)
        ))));
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private function resolvePortalClientId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $role = (string) ($user->property_portal_role ?? '');
        if (! in_array($role, ['tenant', 'landlord'], true)) {
            return null;
        }

        $email = trim((string) ($user->email ?? ''));
        $phone = '';
        if (Schema::hasColumn('users', 'phone')) {
            $phone = trim((string) ($user->phone ?? ''));
        }

        $existing = null;
        if ($email !== '') {
            $existing = LoanClient::query()->clients()->where('email', $email)->first();
        }
        if (! $existing && $phone !== '') {
            $existing = LoanClient::query()->clients()->where('phone', $phone)->first();
        }
        if ($existing) {
            return (int) $existing->id;
        }

        $name = trim((string) ($user->name ?? ''));
        $parts = preg_split('/\s+/', $name) ?: [];
        $firstName = trim((string) ($parts[0] ?? 'Portal'));
        $lastName = trim((string) (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : ucfirst($role)));

        $seed = $email !== '' ? Str::lower($email) : ('u'.$user->id);
        $clientNumber = 'PORTAL-'.strtoupper(substr(md5($role.'|'.$seed), 0, 8));
        while (LoanClient::query()->where('client_number', $clientNumber)->exists()) {
            $clientNumber = 'PORTAL-'.strtoupper(substr(md5($role.'|'.$seed.'|'.Str::random(6)), 0, 8));
        }

        $client = LoanClient::query()->create([
            'client_number' => $clientNumber,
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => $firstName !== '' ? $firstName : 'Portal',
            'last_name' => $lastName !== '' ? $lastName : ucfirst($role),
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'client_status' => 'active',
            'notes' => 'Auto-created from '.$role.' portal handoff.',
        ]);

        return (int) $client->id;
    }

    private function tenantHasPropertyArrears(Request $request): bool
    {
        $user = $request->user();
        if (! $user || (string) ($user->property_portal_role ?? '') !== 'tenant') {
            return false;
        }

        $tenant = $user->pmTenantProfile;
        if (! $tenant) {
            return false;
        }

        $arrears = (float) (PmInvoice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as arrears')
            ->value('arrears') ?? 0.0);

        return $arrears > 0;
    }

    private function stageTransitionError(LoanBookApplication $application, string $nextStage): ?string
    {
        $currentStage = (string) ($application->stage ?? '');
        if ($nextStage === $currentStage) {
            return null;
        }

        $hasLinkedLoan = $application->loan()->exists();

        // Once disbursed, stage must remain disbursed.
        if ($currentStage === LoanBookApplication::STAGE_DISBURSED && $nextStage !== LoanBookApplication::STAGE_DISBURSED) {
            return 'Disbursed applications are locked and cannot be moved back to another stage.';
        }

        // If there is already a booked loan, pipeline stage cannot be moved backward to pre-approval states.
        if ($hasLinkedLoan && in_array($nextStage, [
            LoanBookApplication::STAGE_SUBMITTED,
            LoanBookApplication::STAGE_CREDIT_REVIEW,
            LoanBookApplication::STAGE_DECLINED,
        ], true)) {
            return 'This application already has a linked loan, so its stage cannot be moved to pre-approval states.';
        }

        // "Disbursed" is system-managed only (set after actual disbursement completion).
        if ($nextStage === LoanBookApplication::STAGE_DISBURSED && $currentStage !== LoanBookApplication::STAGE_DISBURSED) {
            return 'Disbursed stage is system-controlled and updates only after a completed disbursement.';
        }

        return null;
    }

}
