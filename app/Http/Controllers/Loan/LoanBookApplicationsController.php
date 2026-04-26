<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanBranch;
use App\Models\LoanClient;
use App\Models\LoanFormFieldDefinition;
use App\Models\LoanProduct;
use App\Models\PmInvoice;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            $requestedCols = collect(explode(',', (string) $request->query('cols', '')))
                ->map(fn (string $col): string => trim($col))
                ->filter()
                ->values();
            $availableCols = [
                'application' => [
                    'label' => 'Application',
                    'value' => fn (LoanBookApplication $app): string => (string) optional($app->submitted_at)->format('d.m.Y, h:i A'),
                ],
                'ref' => [
                    'label' => 'Reference',
                    'value' => fn (LoanBookApplication $app): string => (string) $app->reference,
                ],
                'client' => [
                    'label' => 'Client Name',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->loanClient?->full_name ?? ''),
                ],
                'clientNo' => [
                    'label' => 'Client #',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->loanClient?->client_number ?? ''),
                ],
                'product' => [
                    'label' => 'Product',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->product_name ?? ''),
                ],
                'source' => [
                    'label' => 'Source',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->submission_source ?? ''),
                ],
                'term' => [
                    'label' => 'Term',
                    'value' => fn (LoanBookApplication $app): string => (string) ((int) ($app->term_value ?: $app->term_months)).' '.ucfirst((string) ($app->term_unit ?: 'months')),
                ],
                'amount' => [
                    'label' => 'Amount',
                    'value' => fn (LoanBookApplication $app): string => number_format((float) $app->amount_requested, 2, '.', ''),
                ],
                'guarantor' => [
                    'label' => 'Guarantor',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->guarantor_full_name ?: ($app->loanClient?->guarantor_1_full_name ?? '')),
                ],
                'guarantorContact' => [
                    'label' => 'Guarantor Contact',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->guarantor_phone ?: ($app->loanClient?->guarantor_1_phone ?? '')),
                ],
                'residential' => [
                    'label' => 'Residential Type',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->loanClient?->address ?? ''),
                ],
                'business' => [
                    'label' => 'Business / Purpose',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->purpose ?? ''),
                ],
                'asset' => [
                    'label' => 'Asset List',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->notes ?? ''),
                ],
                'runs' => [
                    'label' => 'Loan Runs',
                    'value' => fn (LoanBookApplication $app): string => '0',
                ],
                'guarantor2' => [
                    'label' => 'Guarantor 2',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->loanClient?->guarantor_2_full_name ?? ''),
                ],
                'guarantor2Contact' => [
                    'label' => 'Guarantor 2 Contact',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->loanClient?->guarantor_2_phone ?? ''),
                ],
                'charges' => [
                    'label' => 'Charges',
                    'value' => fn (LoanBookApplication $app): string => '',
                ],
                'media' => [
                    'label' => 'Attached Media',
                    'value' => fn (LoanBookApplication $app): string => '',
                ],
                'deductions' => [
                    'label' => 'Deductions',
                    'value' => fn (LoanBookApplication $app): string => 'Checkoff(0), Prepayment(0)',
                ],
                'approvedBy' => [
                    'label' => 'Approved By',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->loanClient?->assignedEmployee?->full_name ?? ''),
                ],
                'stage' => [
                    'label' => 'Stage',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->stage ?? ''),
                ],
                'branch' => [
                    'label' => 'Branch',
                    'value' => fn (LoanBookApplication $app): string => (string) ($app->branch ?? ''),
                ],
                'submitted' => [
                    'label' => 'Submitted',
                    'value' => fn (LoanBookApplication $app): string => (string) optional($app->submitted_at)->format('Y-m-d'),
                ],
            ];
            $defaultColOrder = ['ref', 'clientNo', 'client', 'product', 'source', 'amount', 'term', 'stage', 'branch', 'submitted'];
            $selectedCols = $requestedCols->isNotEmpty()
                ? $requestedCols->filter(fn (string $key): bool => array_key_exists($key, $availableCols))->values()->all()
                : $defaultColOrder;
            if ($selectedCols === []) {
                $selectedCols = $defaultColOrder;
            }
            $headers = array_map(fn (string $key): string => (string) $availableCols[$key]['label'], $selectedCols);
            $hasAmountCol = in_array('amount', $selectedCols, true);

            return TabularExport::stream(
                'loanbook-applications-'.now()->format('Ymd_His'),
                $headers,
                function () use ($rows, $selectedCols, $availableCols, $hasAmountCol) {
                    $amountTotal = 0.0;
                    foreach ($rows as $app) {
                        $amountTotal += (float) $app->amount_requested;
                        $row = [];
                        foreach ($selectedCols as $key) {
                            $row[] = (string) $availableCols[$key]['value']($app);
                        }
                        yield $row;
                    }
                    if ($hasAmountCol) {
                        $totalRow = array_fill(0, count($selectedCols), '');
                        $firstColIdx = 0;
                        $amountColIdx = array_search('amount', $selectedCols, true);
                        $totalRow[$firstColIdx] = 'TOTAL';
                        if ($amountColIdx !== false) {
                            $totalRow[$amountColIdx] = number_format($amountTotal, 2, '.', '');
                        }
                        yield $totalRow;
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
                    $amountTotal = 0.0;
                    foreach ($rows as $app) {
                        $amount = (float) $app->amount_requested;
                        $amountTotal += $amount;
                        yield [
                            (string) $app->reference,
                            (string) ($app->loanClient?->client_number ?? ''),
                            (string) ($app->loanClient?->full_name ?? ''),
                            (string) $app->product_name,
                            (string) ($app->submission_source ?? ''),
                            number_format($amount, 2, '.', ''),
                            (string) $app->term_months,
                            (string) $app->stage,
                            (string) ($app->branch ?? ''),
                            (string) optional($app->submitted_at)->format('Y-m-d'),
                        ];
                    }
                    yield ['TOTAL', '', '', '', '', number_format($amountTotal, 2, '.', ''), '', '', '', ''];
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
            ->with('assignedEmployee')
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

        $draftApplication = null;
        $draftId = (int) $request->query('draft_id', 0);
        if ($draftId > 0) {
            $draftApplication = LoanBookApplication::query()
                ->with('loanClient')
                ->find($draftId);
            if ($draftApplication && $draftApplication->loanClient) {
                $this->ensureLoanClientOwner($draftApplication->loanClient);
            } else {
                $draftApplication = null;
            }
        }

        $pendingDrafts = LoanBookApplication::query()
            ->with('loanClient')
            ->where('stage', LoanBookApplication::STAGE_SUBMITTED)
            ->where(function (Builder $query): void {
                $query->whereNull('form_meta->fee_fulfillment_status')
                    ->orWhere('form_meta->fee_fulfillment_status', 'pending');
            });
        $this->scopeByAssignedLoanClient($pendingDrafts, $request->user());
        $pendingDrafts = $pendingDrafts
            ->whereDoesntHave('loan')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

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
            'loanFormMappedFields' => $this->clientLoanMappedFields(),
            'loanFormCustomFields' => $this->clientLoanCustomFields(),
            'draftApplication' => $draftApplication,
            'pendingDrafts' => $pendingDrafts,
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
            'draft_id' => ['nullable', 'integer', 'exists:loan_book_applications,id'],
            'save_as_draft' => ['nullable', 'boolean'],
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
            'suspense_payment_id' => ['nullable', 'integer', 'exists:loan_book_payments,id'],
        ] + $this->clientLoanDynamicValidationRules());
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
        if ($this->applicationFormMetaSupported()) {
            $validated['form_meta'] = $this->resolveClientLoanFormMeta($request);
        }
        $saveAsDraft = $request->boolean('save_as_draft');
        $draftId = (int) ($validated['draft_id'] ?? 0);
        $suspensePaymentId = (int) ($validated['suspense_payment_id'] ?? 0);

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
        $feeSnapshot = $this->requiredFeeSnapshot(
            (string) $validated['product_name'],
            (float) $validated['amount_requested']
        );
        $requiredFeeAmount = (float) ($feeSnapshot['required_total'] ?? 0);
        $selectedPayment = null;
        if ($suspensePaymentId > 0) {
            $selectedPayment = $this->eligibleSuspensePaymentsForClient($client, $draftId)
                ->firstWhere('id', $suspensePaymentId);
            if (! $selectedPayment) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'suspense_payment_id' => 'Selected suspense payment is no longer available.',
                    ])
                    ->withInput();
            }
        }
        $selectedPaymentAmount = (float) ($selectedPayment?->amount ?? 0);
        $feeCovered = $requiredFeeAmount <= 0 || $selectedPaymentAmount >= $requiredFeeAmount;
        if (! $feeCovered && ! $saveAsDraft) {
            return redirect()
                ->back()
                ->withErrors([
                    'suspense_payment_id' => 'Required booking/application/disbursement fees are not fully covered. Attach one eligible suspense payment or save as draft.',
                ])
                ->withInput();
        }

        $validated['submitted_at'] = now();
        $validated['submission_source'] = 'manual_internal';
        $validated['form_meta'] = array_merge((array) ($validated['form_meta'] ?? []), [
            'fee_fulfillment_status' => $feeCovered ? 'fulfilled' : 'pending',
            'fee_required_total' => round($requiredFeeAmount, 2),
            'fee_required_breakdown' => $feeSnapshot['charges'] ?? [],
            'fee_selected_payment_id' => $selectedPayment?->id,
            'fee_selected_payment_amount' => $selectedPayment ? round((float) $selectedPayment->amount, 2) : null,
            'fee_selected_payment_reference' => $selectedPayment?->reference,
            'fee_excess_to_wallet' => $selectedPayment ? round(max(0, (float) $selectedPayment->amount - $requiredFeeAmount), 2) : 0,
        ]);

        DB::transaction(function () use (&$validated, $selectedPayment, $request): void {
            $draftId = (int) ($validated['draft_id'] ?? 0);
            unset($validated['draft_id'], $validated['suspense_payment_id'], $validated['save_as_draft']);
            $application = null;
            if ($draftId > 0) {
                $application = LoanBookApplication::query()->find($draftId);
                if ($application && $application->loanClient) {
                    $this->ensureLoanClientOwner($application->loanClient, $request->user());
                    $validated['reference'] = $application->reference;
                    $application->update($validated);
                }
            }
            if (! $application) {
                $next = (LoanBookApplication::query()->max('id') ?? 0) + 1;
                $validated['reference'] = 'APP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
                $application = LoanBookApplication::query()->create($validated);
            }

            if ($selectedPayment) {
                $payment = LoanBookPayment::query()->lockForUpdate()->find($selectedPayment->id);
                if ($payment && $payment->loan_book_application_id === null) {
                    $existingNotes = trim((string) ($payment->notes ?? ''));
                    $consumeNote = 'Consumed for application fee allocation: '.$application->reference;
                    $payment->update([
                        'loan_book_application_id' => $application->id,
                        'notes' => $existingNotes !== ''
                            ? $existingNotes."\n".$consumeNote
                            : $consumeNote,
                    ]);
                }
            }
        });

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', $feeCovered ? __('Application saved.') : __('Application saved as draft. Complete fee allocation later.'));
    }

    public function suspenseOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['nullable', 'string', 'max:160'],
            'amount_requested' => ['nullable', 'numeric', 'min:0'],
            'draft_id' => ['nullable', 'integer', 'exists:loan_book_applications,id'],
        ]);
        $client = LoanClient::query()->clients()->findOrFail((int) $validated['loan_client_id']);
        $this->ensureLoanClientOwner($client, $request->user());
        $productName = trim((string) ($validated['product_name'] ?? ''));
        $amountRequested = (float) ($validated['amount_requested'] ?? 0);
        $feeSnapshot = $this->requiredFeeSnapshot($productName, $amountRequested);

        $options = $this->eligibleSuspensePaymentsForClient($client, (int) ($validated['draft_id'] ?? 0))
            ->map(fn (LoanBookPayment $payment): array => [
                'id' => (int) $payment->id,
                'reference' => (string) ($payment->reference ?? ('PAY-#'.$payment->id)),
                'amount' => round((float) $payment->amount, 2),
                'payment_kind' => (string) ($payment->payment_kind ?? ''),
                'status' => (string) ($payment->status ?? ''),
                'channel' => (string) ($payment->channel ?? ''),
                'transaction_at' => (string) optional($payment->transaction_at)->format('Y-m-d H:i'),
                'label' => sprintf(
                    '%s | %s %s | %s',
                    (string) ($payment->reference ?? ('PAY-#'.$payment->id)),
                    number_format((float) $payment->amount, 2),
                    (string) ($payment->currency ?? 'KES'),
                    ucfirst(str_replace('_', ' ', (string) ($payment->payment_kind ?? 'normal')))
                ),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'required_fee_total' => round((float) ($feeSnapshot['required_total'] ?? 0), 2),
            'required_fee_breakdown' => $feeSnapshot['charges'] ?? [],
            'options' => $options,
        ]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'default_interest_rate_type' => ['nullable', 'string', 'in:fixed,percent'],
            'default_term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_term_unit' => ['nullable', 'string', 'in:'.implode(',', $this->termUnitOptions())],
            'default_interest_rate_period' => ['nullable', 'string', 'in:'.implode(',', $this->interestRatePeriodOptions())],
        ]);

        $name = trim((string) $validated['name']);
        $description = trim((string) ($validated['description'] ?? ''));
        $hasDefaultTermUnit = Schema::hasColumn('loan_products', 'default_term_unit');
        $hasDefaultRatePeriod = Schema::hasColumn('loan_products', 'default_interest_rate_period');
        $hasDefaultRateType = Schema::hasColumn('loan_products', 'default_interest_rate_type');

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
        if ($hasDefaultRateType) {
            $createPayload['default_interest_rate_type'] = (string) ($validated['default_interest_rate_type'] ?? 'percent');
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
                'default_interest_rate_type' => $hasDefaultRateType ? ($product->default_interest_rate_type ?? 'percent') : 'percent',
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
            'loanFormMappedFields' => $this->clientLoanMappedFields(),
            'loanFormCustomFields' => $this->clientLoanCustomFields(),
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
        ] + $this->clientLoanDynamicValidationRules());
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
        if ($this->applicationFormMetaSupported()) {
            $validated['form_meta'] = $this->resolveClientLoanFormMeta($request, $loan_book_application);
        }
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
     * @return array<string, array{label:string,data_type:string,field_key:string,select_options:list<string>}>
     */
    private function clientLoanMappedFields(): array
    {
        $definitions = $this->clientLoanFormDefinitions();
        $aliases = [
            'product_name' => ['loan product', 'product'],
            'amount_requested' => ['amount', 'requested amount'],
            'term_value' => ['duration', 'term', 'term length'],
            'guarantor_full_name' => ['guarantor name', 'guarantor full name'],
            'guarantor_phone' => ['guarantor contact', 'guarantor phone', 'guarantor tel no'],
            'guarantor_id_number' => ['guarantor id', 'guarantor id no', 'guarantor idno'],
            'applicant_signature_name' => ['applicant sign', 'applicant signature', 'applicant sign (full name)'],
            'applicant_pin_location_code' => ['home / business pin location code', 'pin location code'],
            'guarantor_signature_name' => ['guarantor signature', 'guarantor signature (full name)'],
            'repayment_agreement_accepted' => ['repayment agreement', 'agreement'],
            'client_id_number' => ['client idno', 'idno', 'client id'],
            'loan_officer' => ['loan officer', 'officer'],
        ];

        $mapped = [];
        foreach ($definitions as $field) {
            $label = strtolower(trim((string) $field->label));
            foreach ($aliases as $key => $patterns) {
                if (! in_array($label, $patterns, true) || isset($mapped[$key])) {
                    continue;
                }
                $mapped[$key] = [
                    'label' => (string) $field->label,
                    'data_type' => (string) $field->data_type,
                    'field_key' => (string) $field->field_key,
                    'select_options' => $this->splitSelectOptions((string) ($field->select_options ?? '')),
                ];
            }
        }

        return $mapped;
    }

    /**
     * @return array<int, array{key:string,label:string,data_type:string,select_options:list<string>}>
     */
    private function clientLoanCustomFields(): array
    {
        $definitions = $this->clientLoanFormDefinitions();
        $mappedKeys = collect($this->clientLoanMappedFields())
            ->pluck('field_key')
            ->filter()
            ->values()
            ->all();

        return $definitions
            ->filter(fn (LoanFormFieldDefinition $f): bool => ! in_array((string) $f->field_key, $mappedKeys, true))
            ->map(fn (LoanFormFieldDefinition $f): array => [
                'key' => (string) $f->field_key,
                'label' => (string) $f->label,
                'data_type' => (string) $f->data_type,
                'select_options' => $this->splitSelectOptions((string) ($f->select_options ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function clientLoanDynamicValidationRules(): array
    {
        if (! $this->applicationFormMetaSupported()) {
            return [];
        }

        $rules = [];
        foreach ($this->clientLoanCustomFields() as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $type = (string) ($field['data_type'] ?? LoanFormFieldDefinition::TYPE_ALPHANUMERIC);
            if ($type === LoanFormFieldDefinition::TYPE_IMAGE) {
                $rules["form_files.$key"] = ['nullable', 'file', 'image', 'max:4096'];
                continue;
            }
            if ($type === LoanFormFieldDefinition::TYPE_NUMBER) {
                $rules["form_meta.$key"] = ['nullable', 'numeric'];
                continue;
            }
            if ($type === LoanFormFieldDefinition::TYPE_SELECT) {
                $options = collect((array) ($field['select_options'] ?? []))
                    ->map(fn (string $v): string => trim($v))
                    ->filter()
                    ->values()
                    ->all();
                $rules["form_meta.$key"] = $options !== []
                    ? ['nullable', 'string', \Illuminate\Validation\Rule::in($options)]
                    : ['nullable', 'string', 'max:255'];
                continue;
            }
            $rules["form_meta.$key"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveClientLoanFormMeta(Request $request, ?LoanBookApplication $existing = null): array
    {
        $existingMeta = (array) ($existing?->form_meta ?? []);
        $meta = [];
        foreach ($this->clientLoanCustomFields() as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $type = (string) ($field['data_type'] ?? LoanFormFieldDefinition::TYPE_ALPHANUMERIC);
            if ($type === LoanFormFieldDefinition::TYPE_IMAGE) {
                $inputKey = "form_files.$key";
                if ($request->hasFile($inputKey)) {
                    $file = $request->file($inputKey);
                    if ($file) {
                        $newPath = $file->store('loan-applications/form-meta', 'public');
                        $meta[$key] = $newPath;
                        $oldPath = (string) ($existingMeta[$key] ?? '');
                        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                        continue;
                    }
                }
                if (array_key_exists($key, $existingMeta)) {
                    $meta[$key] = $existingMeta[$key];
                }
                continue;
            }

            $value = $request->input("form_meta.$key");
            if (is_array($value)) {
                $value = '';
            }
            $meta[$key] = trim((string) ($value ?? ''));
        }

        return $meta;
    }

    private function applicationFormMetaSupported(): bool
    {
        return Schema::hasColumn('loan_book_applications', 'form_meta');
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanFormFieldDefinition>
     */
    private function clientLoanFormDefinitions()
    {
        LoanFormFieldDefinition::ensureDefaults(LoanFormFieldDefinition::KIND_CLIENT_LOAN);

        return LoanFormFieldDefinition::query()
            ->where('form_kind', LoanFormFieldDefinition::KIND_CLIENT_LOAN)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function splitSelectOptions(string $options): array
    {
        return collect(explode(',', $options))
            ->map(fn (string $opt): string => trim($opt))
            ->filter()
            ->values()
            ->all();
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
 *     default_interest_rate_type: string,
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
        $hasDefaultRateType = Schema::hasColumn('loan_products', 'default_interest_rate_type');
        $select = ['name', 'default_interest_rate', 'default_term_months'];
        if ($hasDefaultTermUnit) {
            $select[] = 'default_term_unit';
        }
        if ($hasDefaultRatePeriod) {
            $select[] = 'default_interest_rate_period';
        }
        if ($hasDefaultRateType) {
            $select[] = 'default_interest_rate_type';
        }
        $hasCharges = Schema::hasTable('loan_product_charges');

        return LoanProduct::query()
            ->with($hasCharges ? ['charges' => fn ($q) => $q->where('is_active', true)->orderBy('id')] : [])
            ->select($select)
            ->get()
            ->mapWithKeys(fn (LoanProduct $product) => [
                trim((string) $product->name) => [
                    'default_interest_rate' => $product->default_interest_rate !== null ? (float) $product->default_interest_rate : null,
                    'default_interest_rate_type' => $hasDefaultRateType ? (string) ($product->default_interest_rate_type ?? 'percent') : 'percent',
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
                    'charges' => $hasCharges
                        ? $product->charges->map(fn ($charge): array => [
                            'name' => trim((string) $charge->charge_name),
                            'amount_type' => (string) $charge->amount_type,
                            'amount' => (float) $charge->amount,
                            'applies_to_stage' => (string) $charge->applies_to_stage,
                            'applies_to_client_scope' => (string) $charge->applies_to_client_scope,
                        ])->values()->all()
                        : [],
                ],
            ])
            ->all();
    }

    /**
     * @return array{required_total:float,charges:list<array{name:string,stage:string,computed_amount:float}>}
     */
    private function requiredFeeSnapshot(string $productName, float $principal): array
    {
        $name = trim($productName);
        if ($name === '' || ! Schema::hasTable('loan_product_charges')) {
            return ['required_total' => 0.0, 'charges' => []];
        }

        $product = LoanProduct::query()
            ->where('name', $name)
            ->with(['charges' => fn ($q) => $q
                ->where('is_active', true)
                ->whereIn('applies_to_stage', ['application', 'loan', 'disbursement'])
                ->orderBy('id')])
            ->first();
        if (! $product) {
            return ['required_total' => 0.0, 'charges' => []];
        }

        $required = [];
        $total = 0.0;
        $base = max(0.0, $principal);
        foreach ($product->charges as $charge) {
            $amount = (string) $charge->amount_type === 'percent'
                ? $base * ((float) $charge->amount / 100)
                : (float) $charge->amount;
            $computed = round(max(0.0, $amount), 2);
            if ($computed <= 0) {
                continue;
            }
            $total += $computed;
            $required[] = [
                'name' => trim((string) $charge->charge_name),
                'stage' => (string) $charge->applies_to_stage,
                'computed_amount' => $computed,
            ];
        }

        return [
            'required_total' => round($total, 2),
            'charges' => $required,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanBookPayment>
     */
    private function eligibleSuspensePaymentsForClient(LoanClient $client, int $draftId = 0)
    {
        $phoneVariants = $this->phoneVariants((string) ($client->phone ?? ''));

        $unpostedUnassigned = LoanBookPayment::query()
            ->where(function (Builder $query) use ($draftId): void {
                $query->whereNull('loan_book_application_id');
                if ($draftId > 0) {
                    $query->orWhere('loan_book_application_id', $draftId);
                }
            })
            ->whereNull('loan_book_loan_id')
            ->where('status', LoanBookPayment::STATUS_UNPOSTED)
            ->when($phoneVariants !== [], fn (Builder $q) => $q->whereIn('payer_msisdn', $phoneVariants))
            ->orderByDesc('transaction_at')
            ->get();

        $overpayments = LoanBookPayment::query()
            ->with('loan')
            ->where(function (Builder $query) use ($draftId): void {
                $query->whereNull('loan_book_application_id');
                if ($draftId > 0) {
                    $query->orWhere('loan_book_application_id', $draftId);
                }
            })
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->where('payment_kind', LoanBookPayment::KIND_OVERPAYMENT)
            ->whereHas('loan', fn (Builder $q) => $q->where('loan_client_id', $client->id))
            ->orderByDesc('transaction_at')
            ->get();

        return $overpayments
            ->concat($unpostedUnassigned)
            ->unique('id')
            ->values();
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];
        if (str_starts_with($digits, '254') && strlen($digits) >= 12) {
            $variants[] = '0'.substr($digits, 3);
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $variants[] = '254'.substr($digits, 1);
        }

        return array_values(array_unique(array_filter($variants)));
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
