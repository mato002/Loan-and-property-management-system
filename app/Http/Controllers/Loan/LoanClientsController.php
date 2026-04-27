<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\ClientInteraction;
use App\Models\ClientTransfer;
use App\Models\DefaultClientGroup;
use App\Models\Employee;
use App\Models\LoanFormFieldDefinition;
use App\Models\LoanBranch;
use App\Models\LoanBookApplication;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\LoanRegion;
use App\Notifications\Loan\LoanWorkflowNotification;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LoanClientsController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $q = LoanClient::query()
            ->clients()
            ->with('assignedEmployee')
            ->withCount('loanBookLoans')
            ->orderBy('last_name')
            ->orderBy('first_name');
        $this->scopeLoanClientsToUser($q, $request->user());

        $search = trim((string) $request->query('q', ''));
        $branch = trim((string) $request->query('branch', ''));
        $status = trim((string) $request->query('status', ''));
        $employeeId = (int) $request->query('employee_id', 0);
        $perPage = min(200, max(10, (int) $request->query('per_page', 15)));

        if ($search !== '') {
            $q->where(function ($query) use ($search) {
                $query
                    ->where('client_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }
        if ($branch !== '') {
            $q->where('branch', $branch);
        }
        if ($status !== '') {
            $q->where('client_status', $status);
        }
        if ($employeeId > 0) {
            $q->where('assigned_employee_id', $employeeId);
        }

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $q)->limit(5000)->get();
            $requestedCols = collect(explode(',', (string) $request->query('cols', '')))
                ->map(fn (string $col): string => trim($col))
                ->filter()
                ->values();
            $availableCols = [
                'number' => [
                    'label' => 'Client Number',
                    'value' => fn (LoanClient $client): string => (string) ($client->client_number ?? ''),
                ],
                'name' => [
                    'label' => 'Client Name',
                    'value' => fn (LoanClient $client): string => (string) ($client->full_name ?? ''),
                ],
                'idNo' => [
                    'label' => 'ID Number',
                    'value' => fn (LoanClient $client): string => (string) ($client->id_number ?? ''),
                ],
                'mPoints' => [
                    'label' => 'M-Points',
                    'value' => fn (LoanClient $client): string => '0',
                ],
                'branch' => [
                    'label' => 'Branch',
                    'value' => fn (LoanClient $client): string => (string) ($client->branch ?? ''),
                ],
                'cycles' => [
                    'label' => 'Cycles',
                    'value' => fn (LoanClient $client): string => (string) ((int) ($client->loan_book_loans_count ?? 0)),
                ],
                'gender' => [
                    'label' => 'Gender',
                    'value' => fn (LoanClient $client): string => (string) ($client->gender ? ucfirst((string) $client->gender) : ''),
                ],
                'assigned' => [
                    'label' => 'Loans Officer',
                    'value' => fn (LoanClient $client): string => (string) ($client->assignedEmployee?->full_name ?? ''),
                ],
                'status' => [
                    'label' => 'Status',
                    'value' => fn (LoanClient $client): string => (string) ($client->client_status ?? ''),
                ],
                'contact' => [
                    'label' => 'Contact',
                    'value' => fn (LoanClient $client): string => trim((string) (($client->phone ?? '').' '.($client->email ?? ''))),
                ],
                'kinContact' => [
                    'label' => 'Kin Contact',
                    'value' => fn (LoanClient $client): string => (string) ($client->next_of_kin_contact ?? ''),
                ],
                'nextOfKin' => [
                    'label' => 'Next Of Kin',
                    'value' => fn (LoanClient $client): string => (string) ($client->next_of_kin_name ?? ''),
                ],
            ];
            $defaultColOrder = ['number', 'name', 'mPoints', 'idNo', 'branch', 'cycles', 'gender', 'contact', 'kinContact', 'nextOfKin', 'assigned', 'status'];
            $selectedCols = $requestedCols->isNotEmpty()
                ? $requestedCols->filter(fn (string $key): bool => array_key_exists($key, $availableCols))->values()->all()
                : $defaultColOrder;
            if ($selectedCols === []) {
                $selectedCols = $defaultColOrder;
            }
            $headers = array_map(fn (string $key): string => (string) $availableCols[$key]['label'], $selectedCols);

            return TabularExport::stream(
                'loan-clients-'.now()->format('Ymd_His'),
                $headers,
                function () use ($rows, $selectedCols, $availableCols) {
                    foreach ($rows as $client) {
                        $row = [];
                        foreach ($selectedCols as $key) {
                            $row[] = (string) $availableCols[$key]['value']($client);
                        }
                        yield $row;
                    }
                },
                $export,
                [
                    'title' => 'Client register',
                    'subtitle' => 'Loan clients, assignments, and contact details.',
                    'summary' => [
                        'Rows' => (string) $rows->count(),
                        'Filters' => trim(collect([
                            $search !== '' ? "Search: {$search}" : null,
                            $branch !== '' ? "Branch: {$branch}" : null,
                            $status !== '' ? "Status: {$status}" : null,
                            $employeeId > 0 ? 'Assigned: filtered' : null,
                        ])->filter()->implode(' | ')) ?: 'None',
                    ],
                ]
            );
        }

        $clients = $q->paginate($perPage)->withQueryString();
        $branchOptions = LoanClient::query()
            ->clients()
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch');
        $employees = $this->employeesForSelect();

        return view('loan.clients.index', compact(
            'clients',
            'search',
            'branch',
            'status',
            'employeeId',
            'perPage',
            'branchOptions',
            'employees'
        ));
    }

    public function create(): View
    {
        $employees = $this->employeesForSelect();

        return view('loan.clients.create', [
            'employees' => $employees,
            'branchOptions' => $this->branchOptions(),
            'biodataLabels' => $this->clientBiodataLabels(),
            'biodataDynamicFields' => $this->clientBiodataDynamicFields(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateClientPayload($request, null, LoanClient::KIND_CLIENT);
        $validated['branch'] = $this->syncBranchDirectory($validated['branch'] ?? null);
        $validated = array_merge($validated, $this->handleClientImageUploads($request));
        $validated['biodata_meta'] = $this->resolveDynamicBiodataMeta($request);
        $validated['client_number'] = $this->generateClientNumber();
        $validated['kind'] = LoanClient::KIND_CLIENT;
        $validated['lead_status'] = null;
        $validated['assigned_employee_id'] = $this->resolveAssignedEmployeeForCreate(
            $request,
            $validated['assigned_employee_id'] ?? null
        );
        if (empty($validated['client_status'])) {
            $validated['client_status'] = 'active';
        }

        LoanClient::create($validated);

        return redirect()
            ->route('loan.clients.index')
            ->with('status', 'Client saved successfully.');
    }

    public function show(LoanClient $loan_client): View
    {
        $this->ensureLoanClientAccessible($loan_client);

        $loan_client->load([
            'assignedEmployee',
            'defaultGroups',
            'interactions' => fn ($q) => $q->with('user')->orderByDesc('interacted_at')->limit(8),
            'loanBookLoans' => fn ($q) => $q
                ->with('application')
                ->with('disbursements')
                ->with('collectionAgent')
                ->with(['processedRepayments' => fn ($payments) => $payments->orderBy('transaction_at')->orderBy('id')])
                ->withSum('processedRepayments', 'amount')
                ->orderByDesc('disbursed_at')
                ->limit(50),
        ]);

        $collectionAgents = Employee::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('job_title', 'like', '%collect%')
                    ->orWhereHas('staffGroups', function (Builder $groupQuery): void {
                        $groupQuery
                            ->where('name', 'like', '%collect%')
                            ->orWhere('description', 'like', '%collect%');
                    });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'job_title', 'branch']);

        $recentApplications = LoanBookApplication::query()
            ->where('loan_client_id', $loan_client->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $recentDisbursements = LoanBookDisbursement::query()
            ->with('loan')
            ->whereHas('loan', fn (Builder $query) => $query->where('loan_client_id', $loan_client->id))
            ->orderByDesc('disbursed_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $recentPayments = LoanBookPayment::query()
            ->with('loan')
            ->whereHas('loan', fn (Builder $query) => $query->where('loan_client_id', $loan_client->id))
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $loanHistory = $loan_client->loanBookLoans ?? collect();
        $activeLoans = $loanHistory->whereIn('status', [
            LoanBookLoan::STATUS_ACTIVE,
            LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
            LoanBookLoan::STATUS_RESTRUCTURED,
        ]);
        $totalOutstanding = (float) $activeLoans->sum(fn (LoanBookLoan $loan): float => (float) ($loan->balance ?? 0));
        $totalArrearsDays = (float) $activeLoans->sum(fn (LoanBookLoan $loan): float => (float) ($loan->dpd ?? 0));
        $totalPrincipal = (float) $loanHistory->sum(fn (LoanBookLoan $loan): float => (float) ($loan->principal ?? 0));
        $totalRepaid = (float) $loanHistory->sum(fn (LoanBookLoan $loan): float => (float) ($loan->processed_repayments_sum_amount ?? 0));
        $averageDpd = (float) $activeLoans->avg(fn (LoanBookLoan $loan): float => (float) ($loan->dpd ?? 0));
        $creditScore = (int) max(420, min(850, round(790 - ($averageDpd * 7))));
        $completionNumerator = (int) $loanHistory->sum(
            fn (LoanBookLoan $loan): int => min((int) ($loan->term_value ?? 0), (int) (($loan->processedRepayments ?? collect())->count()))
        );
        $completionDenominator = (int) max(1, $loanHistory->sum(fn (LoanBookLoan $loan): int => (int) ($loan->term_value ?? 0)));
        $completionPercent = (int) min(100, round(($completionNumerator / max(1, $completionDenominator)) * 100));

        $walletBalance = (float) LoanBookPayment::query()
            ->processedQueue()
            ->where('channel', 'like', 'wallet%')
            ->whereHas('loan', fn (Builder $query) => $query->where('loan_client_id', $loan_client->id))
            ->sum('amount');

        // Derive realized income from actual repayment allocation:
        // payment allocation in this system settles fees, then interest, then principal.
        // So income earned = net repaid - principal recovered.
        $principalRecovered = (float) $loanHistory->sum(function (LoanBookLoan $loan): float {
            $principal = (float) ($loan->principal ?? 0);
            $principalOutstanding = max(0.0, (float) ($loan->principal_outstanding ?? $principal));

            return max(0.0, $principal - $principalOutstanding);
        });
        $lifetimeEarnings = max(0.0, $totalRepaid - $principalRecovered);

        $dashboardMetrics = [
            'total_outstanding' => $totalOutstanding,
            'total_arrears_days' => $totalArrearsDays,
            'total_principal' => $totalPrincipal,
            'total_repaid' => $totalRepaid,
            'loan_cycles' => (int) $loanHistory->count(),
            'completion_numerator' => $completionNumerator,
            'completion_denominator' => $completionDenominator,
            'completion_percent' => $completionPercent,
            'credit_score' => $creditScore,
            'wallet_balance' => $walletBalance,
            'lifetime_value' => $lifetimeEarnings,
        ];

        return view('loan.clients.show', [
            'loan_client' => $loan_client,
            'biodataLabels' => $this->clientBiodataLabels(),
            'recentApplications' => $recentApplications,
            'recentDisbursements' => $recentDisbursements,
            'recentPayments' => $recentPayments,
            'collectionAgents' => $collectionAgents,
            'dashboardMetrics' => $dashboardMetrics,
        ]);
    }

    public function assignLoanCollectionAgent(Request $request, LoanClient $loan_client, LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if ((int) $loan_book_loan->loan_client_id !== (int) $loan_client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'collection_agent_employee_id' => ['required', 'exists:employees,id'],
        ]);

        $loan_book_loan->update([
            'collection_agent_employee_id' => (int) $validated['collection_agent_employee_id'],
        ]);

        return back()->with('status', 'Collection agent assigned successfully.');
    }

    public function edit(LoanClient $loan_client): View
    {
        $this->ensureLoanClientAccessible($loan_client);

        $employees = $this->employeesForSelect();

        return view('loan.clients.edit', [
            'loan_client' => $loan_client,
            'employees' => $employees,
            'branchOptions' => $this->branchOptions(),
            'biodataLabels' => $this->clientBiodataLabels(),
            'biodataDynamicFields' => $this->clientBiodataDynamicFields(),
        ]);
    }

    public function update(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);

        $validated = $this->validateClientPayload($request, $loan_client, $loan_client->kind);
        $validated['branch'] = $this->syncBranchDirectory($validated['branch'] ?? null);
        $validated = array_merge($validated, $this->handleClientImageUploads($request, $loan_client));
        $validated['biodata_meta'] = $this->resolveDynamicBiodataMeta($request, $loan_client);
        // Client numbers are system-assigned and immutable after creation.
        $validated['client_number'] = $loan_client->client_number;
        $loan_client->update($validated);

        $route = $loan_client->kind === LoanClient::KIND_LEAD
            ? 'loan.clients.leads'
            : 'loan.clients.index';

        return redirect()
            ->route($route)
            ->with('status', 'Record updated.');
    }

    public function destroy(LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);

        $wasLead = $loan_client->kind === LoanClient::KIND_LEAD;
        $loan_client->delete();

        return redirect()
            ->route($wasLead ? 'loan.clients.leads' : 'loan.clients.index')
            ->with('status', 'Record removed.');
    }

    public function leads(Request $request): View
    {
        $q = LoanClient::query()
            ->leads()
            ->with('assignedEmployee')
            ->orderByDesc('created_at');
        $this->scopeLoanClientsToUser($q, $request->user());

        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($query) use ($search) {
                $query
                    ->where('client_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $leads = $q->paginate(15)->withQueryString();

        return view('loan.clients.leads', compact('leads'));
    }

    public function leadsCreate(): View
    {
        $employees = $this->employeesForSelect();

        return view('loan.clients.leads-create', [
            'employees' => $employees,
            'branchOptions' => $this->branchOptions(),
        ]);
    }

    public function leadsStore(Request $request): RedirectResponse
    {
        $validated = $this->validateClientPayload($request, null, LoanClient::KIND_LEAD);
        $validated['branch'] = $this->syncBranchDirectory($validated['branch'] ?? null);
        $validated['client_number'] = $this->generateClientNumber('LD');
        $validated['kind'] = LoanClient::KIND_LEAD;
        $validated['client_status'] = 'n/a';
        $validated['assigned_employee_id'] = $this->resolveAssignedEmployeeForCreate(
            $request,
            $validated['assigned_employee_id'] ?? null
        );
        if (empty($validated['lead_status'])) {
            $validated['lead_status'] = 'new';
        }

        LoanClient::create($validated);

        return redirect()
            ->route('loan.clients.leads')
            ->with('status', 'Lead captured.');
    }

    public function branchStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
        ]);

        $name = trim((string) $validated['name']);
        if ($name === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Branch name is required.',
            ], 422);
        }

        if (Schema::hasTable('loan_branches') && Schema::hasTable('loan_regions')) {
            $region = LoanRegion::query()->orderBy('name')->first();
            if (! $region) {
                $region = LoanRegion::query()->create([
                    'name' => 'Default Region',
                    'description' => 'Auto-created for quick branch setup.',
                    'is_active' => true,
                ]);
            }

            LoanBranch::query()->firstOrCreate(
                ['name' => $name],
                [
                    'loan_region_id' => $region->id,
                    'is_active' => true,
                ]
            );
        }

        return response()->json([
            'ok' => true,
            'branch' => [
                'name' => $name,
            ],
        ]);
    }

    public function leadsConvert(LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);

        if ($loan_client->kind !== LoanClient::KIND_LEAD) {
            return back()->with('status', 'Only leads can be converted.');
        }

        $loan_client->update([
            'kind' => LoanClient::KIND_CLIENT,
            'lead_status' => null,
            'client_status' => 'active',
            'converted_at' => now(),
        ]);

        return redirect()
            ->route('loan.clients.show', $loan_client)
            ->with('status', 'Lead converted to client.');
    }

    public function transfer(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $clients = LoanClient::query()
            ->clients()
            ->with('assignedEmployee')
            ->when(! $this->canAccessAllLoanData(auth()->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId(auth()->user())))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $employees = $this->employeesForSelect();
        $sourceOfficerCounts = LoanClient::query()
            ->clients()
            ->selectRaw('assigned_employee_id, COUNT(*) as c')
            ->whereNotNull('assigned_employee_id')
            ->whereIn('client_status', ['active', 'dormant'])
            ->groupBy('assigned_employee_id')
            ->pluck('c', 'assigned_employee_id');

        $recentTransfers = ClientTransfer::query()
            ->with(['loanClient', 'fromEmployee', 'toEmployee', 'transferredByUser'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return TabularExport::stream(
                'loan-client-transfers-'.now()->format('Ymd_His'),
                ['Date', 'Client', 'Client Number', 'From Branch', 'To Branch', 'From Officer', 'To Officer', 'Transferred By', 'Reason'],
                function () use ($recentTransfers) {
                    foreach ($recentTransfers as $t) {
                        yield [
                            (string) optional($t->created_at)->format('Y-m-d H:i:s'),
                            (string) ($t->loanClient?->full_name ?? ''),
                            (string) ($t->loanClient?->client_number ?? ''),
                            (string) ($t->from_branch ?? ''),
                            (string) ($t->to_branch ?? ''),
                            (string) ($t->fromEmployee?->full_name ?? ''),
                            (string) ($t->toEmployee?->full_name ?? ''),
                            (string) ($t->transferredByUser?->name ?? ''),
                            (string) ($t->reason ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $branchOptions = $this->branchOptions();

        return view('loan.clients.transfer', compact('clients', 'employees', 'recentTransfers', 'sourceOfficerCounts', 'branchOptions'));
    }

    public function transferStore(Request $request): RedirectResponse
    {
        $mode = strtolower(trim((string) $request->input('mode', 'single')));

        if ($mode === 'bulk_active') {
            $validated = $request->validate([
                'from_employee_id' => ['required', 'exists:employees,id'],
                'to_employee_id' => ['required', 'exists:employees,id', 'different:from_employee_id'],
                'include_dormant' => ['nullable', 'boolean'],
                'reason' => ['nullable', 'string', 'max:2000'],
            ]);

            $fromEmployeeId = (int) $validated['from_employee_id'];
            $toEmployeeId = (int) $validated['to_employee_id'];
            $includeDormant = (bool) ($validated['include_dormant'] ?? false);
            $currentEmployeeId = $this->resolveLoanEmployeeId($request->user());

            if (! $this->canAccessAllLoanData($request->user()) && ($currentEmployeeId === null || $fromEmployeeId !== (int) $currentEmployeeId)) {
                return back()
                    ->withErrors(['from_employee_id' => 'You can only transfer clients from your own portfolio.'])
                    ->withInput();
            }

            $clientsQuery = LoanClient::query()
                ->clients()
                ->where('assigned_employee_id', $fromEmployeeId)
                ->whereIn('client_status', $includeDormant ? ['active', 'dormant'] : ['active']);
            $this->scopeByAssignedLoanClient($clientsQuery, $request->user());
            $clients = $clientsQuery->get();

            if ($clients->isEmpty()) {
                return back()
                    ->withErrors(['from_employee_id' => 'No matching clients found for the selected source officer and status filter.'])
                    ->withInput();
            }

            foreach ($clients as $client) {
                ClientTransfer::create([
                    'loan_client_id' => $client->id,
                    'from_branch' => $client->branch,
                    'to_branch' => $client->branch,
                    'from_employee_id' => $client->assigned_employee_id,
                    'to_employee_id' => $toEmployeeId,
                    'reason' => $validated['reason'] ?? ($includeDormant ? 'Bulk transfer (active + dormant clients).' : 'Bulk transfer (active clients).'),
                    'transferred_by' => $request->user()->id,
                ]);

                $client->update([
                    'assigned_employee_id' => $toEmployeeId,
                ]);
            }

            $request->user()?->notify(new LoanWorkflowNotification(
                'Bulk transfer completed',
                $clients->count().' client(s) were transferred successfully.',
                route('loan.clients.transfer')
            ));

            return redirect()
                ->route('loan.clients.transfer')
                ->with('status', 'Bulk transfer complete. '.$clients->count().' client(s) moved.');
        }

        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'to_branch' => ['nullable', 'string', 'max:120'],
            'to_employee_id' => ['nullable', 'exists:employees,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['to_branch'] = $this->syncBranchDirectory($validated['to_branch'] ?? null);

        $client = LoanClient::query()->findOrFail($validated['loan_client_id']);
        $this->ensureLoanClientAccessible($client);
        if ($client->kind !== LoanClient::KIND_CLIENT) {
            return back()->withErrors(['loan_client_id' => 'Transfers apply to clients only.'])->withInput();
        }

        ClientTransfer::create([
            'loan_client_id' => $client->id,
            'from_branch' => $client->branch,
            'to_branch' => $validated['to_branch'] ?? null,
            'from_employee_id' => $client->assigned_employee_id,
            'to_employee_id' => $validated['to_employee_id'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'transferred_by' => $request->user()->id,
        ]);

        $client->update([
            'branch' => $validated['to_branch'] ?? $client->branch,
            'assigned_employee_id' => $validated['to_employee_id'] ?? $client->assigned_employee_id,
        ]);

        $request->user()?->notify(new LoanWorkflowNotification(
            'Client transfer completed',
            'Client '.$client->full_name.' was transferred successfully.',
            route('loan.clients.transfer')
        ));

        return redirect()
            ->route('loan.clients.transfer')
            ->with('status', 'Client transfer recorded and portfolio updated.');
    }

    public function defaultGroups(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $groups = DefaultClientGroup::query()
            ->withCount('loanClients')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('loan.clients.default-groups', compact('groups', 'q'));
    }

    public function defaultGroupsCreate(): View
    {
        $baseClientQuery = LoanClient::query()
            ->clients()
            ->select(['id', 'client_number', 'first_name', 'last_name', 'phone', 'id_number'])
            ->orderBy('last_name')
            ->orderBy('first_name');
        $this->scopeLoanClientsToUser($baseClientQuery, auth()->user());

        return view('loan.clients.default-groups-create', [
            'clientOptions' => (clone $baseClientQuery)->limit(5000)->get(),
            'totalClientCount' => (clone $baseClientQuery)->count(),
        ]);
    }

    public function defaultGroupsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'select_all_clients' => ['nullable', 'boolean'],
            'loan_client_ids' => ['nullable', 'array'],
            'loan_client_ids.*' => ['integer', 'exists:loan_clients,id'],
        ]);

        $group = DefaultClientGroup::create($validated);

        $selectAll = (bool) ($validated['select_all_clients'] ?? false);
        $selectedIds = collect($validated['loan_client_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($selectAll) {
            $attachQuery = LoanClient::query()->clients()->select('id');
            $this->scopeLoanClientsToUser($attachQuery, $request->user());
            $selectedIds = $attachQuery->pluck('id');
        }
        if ($selectedIds->isNotEmpty()) {
            $group->loanClients()->syncWithoutDetaching($selectedIds->all());
        }

        return redirect()
            ->route('loan.clients.default_groups.show', $group)
            ->with('status', 'Group created. Add members below.');
    }

    public function defaultGroupsShow(Request $request, DefaultClientGroup $default_client_group): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $q = trim((string) $request->query('q', ''));
        $employeeId = (int) $request->query('employee_id', 0);

        $memberQuery = $default_client_group->loanClients()
            ->clients()
            ->with('assignedEmployee')
            ->withSum(['loanBookLoans as total_balance' => function ($loan) {
                $loan->whereIn('status', [
                    \App\Models\LoanBookLoan::STATUS_ACTIVE,
                    \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
                    \App\Models\LoanBookLoan::STATUS_RESTRUCTURED,
                ]);
            }], 'balance')
            ->withMax(['loanBookLoans as max_dpd' => function ($loan) {
                $loan->whereIn('status', [
                    \App\Models\LoanBookLoan::STATUS_ACTIVE,
                    \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
                    \App\Models\LoanBookLoan::STATUS_RESTRUCTURED,
                ]);
            }], 'dpd')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('client_number', 'like', '%'.$q.'%')
                        ->orWhere('first_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('id_number', 'like', '%'.$q.'%');
                });
            })
            ->when($employeeId > 0, fn ($builder) => $builder->where('assigned_employee_id', $employeeId))
            ->orderBy('last_name')
            ->orderBy('first_name');

        $members = $memberQuery->paginate(30)->withQueryString();

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $memberQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loan-client-group-members-'.now()->format('Ymd_His'),
                ['Group', 'Client', 'Contact', 'ID Number', 'Loan Officer', 'Balance', 'Days'],
                function () use ($rows, $default_client_group) {
                    foreach ($rows as $client) {
                        yield [
                            (string) $default_client_group->name,
                            (string) $client->full_name,
                            (string) ($client->phone ?? ''),
                            (string) ($client->id_number ?? ''),
                            (string) ($client->assignedEmployee?->full_name ?? ''),
                            number_format((float) ($client->total_balance ?? 0), 2, '.', ''),
                            (string) ((int) ($client->max_dpd ?? 0)),
                        ];
                    }
                },
                $export
            );
        }

        $default_client_group->loadCount('loanClients');

        $availableClients = LoanClient::query()
            ->clients()
            ->whereNotIn('id', $default_client_group->loanClients->pluck('id'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $groups = DefaultClientGroup::query()->orderBy('name')->get(['id', 'name']);
        $employees = $this->employeesForSelect();

        return view('loan.clients.default-groups-show', compact(
            'default_client_group',
            'availableClients',
            'groups',
            'employees',
            'members',
            'q',
            'employeeId'
        ));
    }

    public function defaultGroupsEdit(DefaultClientGroup $default_client_group): View
    {
        $default_client_group->load('loanClients:id');
        $selectedIds = $default_client_group->loanClients->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $baseClientQuery = LoanClient::query()
            ->clients()
            ->select(['id', 'client_number', 'first_name', 'last_name', 'phone', 'id_number'])
            ->orderBy('last_name')
            ->orderBy('first_name');
        $this->scopeLoanClientsToUser($baseClientQuery, auth()->user());

        return view('loan.clients.default-groups-edit', [
            'default_client_group' => $default_client_group,
            'clientOptions' => (clone $baseClientQuery)->limit(5000)->get(),
            'totalClientCount' => (clone $baseClientQuery)->count(),
            'selectedClientIds' => $selectedIds,
        ]);
    }

    public function defaultGroupsUpdate(Request $request, DefaultClientGroup $default_client_group): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'select_all_clients' => ['nullable', 'boolean'],
            'loan_client_ids' => ['nullable', 'array'],
            'loan_client_ids.*' => ['integer', 'exists:loan_clients,id'],
        ]);

        $default_client_group->update($validated);

        $selectAll = (bool) ($validated['select_all_clients'] ?? false);
        $selectedIds = collect($validated['loan_client_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($selectAll) {
            $attachQuery = LoanClient::query()->clients()->select('id');
            $this->scopeLoanClientsToUser($attachQuery, $request->user());
            $selectedIds = $attachQuery->pluck('id');
        }
        if ($selectedIds->isNotEmpty()) {
            $default_client_group->loanClients()->sync($selectedIds->all());
        }

        return redirect()
            ->route('loan.clients.default_groups.show', $default_client_group)
            ->with('status', 'Group updated.');
    }

    public function defaultGroupsDestroy(DefaultClientGroup $default_client_group): RedirectResponse
    {
        $default_client_group->delete();

        return redirect()
            ->route('loan.clients.default_groups')
            ->with('status', 'Group removed.');
    }

    public function defaultGroupsMemberStore(Request $request, DefaultClientGroup $default_client_group): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
        ]);

        $client = LoanClient::query()->findOrFail($validated['loan_client_id']);
        if ($client->kind !== LoanClient::KIND_CLIENT) {
            return back()->with('status', 'Only clients can join default groups.');
        }

        $default_client_group->loanClients()->syncWithoutDetaching([$client->id]);

        return back()->with('status', 'Member added to group.');
    }

    public function defaultGroupsMemberDestroy(DefaultClientGroup $default_client_group, LoanClient $loan_client): RedirectResponse
    {
        $default_client_group->loanClients()->detach($loan_client->id);

        return back()->with('status', 'Member removed from group.');
    }

    public function interactions(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $baseQuery = ClientInteraction::query()
            ->with(['loanClient.assignedEmployee', 'user'])
            ->orderByDesc('interacted_at');
        $this->scopeByAssignedLoanClient($baseQuery, $request->user());

        $type = trim((string) $request->get('type', ''));
        $from = trim((string) $request->get('from', ''));
        $to = trim((string) $request->get('to', ''));
        $sourceUserId = (int) $request->get('source_user_id', 0);
        $search = trim((string) $request->get('q', ''));

        if ($type !== '') {
            $baseQuery->where('interaction_type', $type);
        }
        if ($from !== '') {
            $baseQuery->whereDate('interacted_at', '>=', $from);
        }
        if ($to !== '') {
            $baseQuery->whereDate('interacted_at', '<=', $to);
        }
        if ($sourceUserId > 0) {
            $baseQuery->where('user_id', $sourceUserId);
        }
        if ($search !== '') {
            $baseQuery->whereHas('loanClient', function ($clientQ) use ($search) {
                $clientQ->where('client_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        // Show one row per client: the latest interaction after applying filters.
        $latestPerClientIds = (clone $baseQuery)
            ->selectRaw('MAX(id) as id')
            ->groupBy('loan_client_id');
        $q = ClientInteraction::query()
            ->with(['loanClient.assignedEmployee', 'user'])
            ->whereIn('id', $latestPerClientIds)
            ->orderByDesc('interacted_at');

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $q)->limit(5000)->get();

            return TabularExport::stream(
                'loan-client-interactions-'.now()->format('Ymd_His'),
                ['Client', 'Loan Officer', 'Comment', 'Source', 'Client Status', 'Type', 'Interaction Date'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield [
                            (string) ($row->loanClient?->full_name ?? ''),
                            (string) ($row->loanClient?->assignedEmployee?->full_name ?? ''),
                            (string) ($row->notes ?: ($row->subject ?? '')),
                            (string) ($row->user?->name ?? ''),
                            ucfirst((string) ($row->loanClient?->client_status ?? 'n/a')),
                            ucfirst((string) ($row->interaction_type ?? '')),
                            (string) optional($row->interacted_at)->format('Y-m-d H:i:s'),
                        ];
                    }
                },
                $export
            );
        }

        $interactions = $q->paginate(25)->withQueryString();
        $sourceUsers = ClientInteraction::query()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->with('user:id,name')
            ->orderBy('user_id')
            ->get()
            ->map(fn (ClientInteraction $row) => $row->user)
            ->filter()
            ->unique('id')
            ->values();

        return view('loan.clients.interactions', compact(
            'interactions',
            'sourceUsers',
            'type',
            'from',
            'to',
            'sourceUserId',
            'search'
        ));
    }

    public function interactionsShow(ClientInteraction $client_interaction): View
    {
        $client_interaction->load(['loanClient.assignedEmployee', 'user']);
        if (! $client_interaction->loanClient) {
            abort(404);
        }

        $this->ensureLoanClientAccessible($client_interaction->loanClient);

        return view('loan.clients.interactions-show', [
            'interaction' => $client_interaction,
        ]);
    }

    public function interactionsCreate(): View
    {
        $people = LoanClient::query()
            ->when(! $this->canAccessAllLoanData(auth()->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId(auth()->user())))
            ->orderBy('kind')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.clients.interactions-create', compact('people'));
    }

    public function interactionsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'interaction_type' => ['required', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'interacted_at' => ['required', 'date'],
        ]);

        ClientInteraction::create([
            'loan_client_id' => $validated['loan_client_id'],
            'user_id' => $request->user()->id,
            'interaction_type' => $validated['interaction_type'],
            'subject' => $validated['subject'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'interacted_at' => $validated['interacted_at'],
        ]);

        $client = LoanClient::query()->find($validated['loan_client_id']);
        if ($client) {
            $request->user()?->notify(new LoanWorkflowNotification(
                'Interaction posted',
                'Interaction logged for '.$client->full_name.'.',
                route('loan.clients.interactions.for_client.create', $client)
            ));
        }

        return redirect()
            ->route('loan.clients.interactions')
            ->with('status', 'Interaction logged.');
    }

    public function interactionCreateForClient(LoanClient $loan_client): View
    {
        $this->ensureLoanClientAccessible($loan_client);

        $interactions = ClientInteraction::query()
            ->with('user')
            ->where('loan_client_id', $loan_client->id)
            ->orderByDesc('interacted_at')
            ->paginate(30)
            ->withQueryString();

        return view('loan.clients.interaction-for-client', compact('loan_client', 'interactions'));
    }

    public function interactionStoreForClient(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);

        $validated = $request->validate([
            'interaction_type' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:5000'],
            'interacted_at' => ['nullable', 'date'],
        ]);

        ClientInteraction::create([
            'loan_client_id' => $loan_client->id,
            'user_id' => $request->user()->id,
            'interaction_type' => $validated['interaction_type'] ?? 'other',
            'subject' => $validated['subject'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'interacted_at' => $validated['interacted_at'] ?? now(),
        ]);

        $request->user()?->notify(new LoanWorkflowNotification(
            'Interaction posted',
            'New comment posted for '.$loan_client->full_name.'.',
            route('loan.clients.interactions.for_client.create', $loan_client)
        ));

        return redirect()
            ->route('loan.clients.interactions.for_client.create', $loan_client)
            ->with('status', 'Interaction logged.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClientPayload(Request $request, ?LoanClient $existing, string $kind): array
    {
        $id = $existing?->id;

        $rules = [
            'client_number' => [
                $existing ? 'required' : 'nullable',
                'string',
                'max:50',
                'unique:loan_clients,client_number'.($id ? ','.$id.',id' : ''),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40', 'unique:loan_clients,phone'.($id ? ','.$id.',id' : '')],
            'email' => ['nullable', 'email', 'max:255', 'unique:loan_clients,email'.($id ? ','.$id.',id' : '')],
            'id_number' => ['nullable', 'string', 'max:80', 'unique:loan_clients,id_number'.($id ? ','.$id.',id' : '')],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'next_of_kin_name' => ['nullable', 'string', 'max:200'],
            'next_of_kin_contact' => ['nullable', 'string', 'max:40'],
            'client_photo' => ['nullable', 'image', 'max:4096'],
            'id_front_photo' => ['nullable', 'image', 'max:4096'],
            'id_back_photo' => ['nullable', 'image', 'max:4096'],
            'biodata_meta' => ['nullable', 'array'],
            'address' => ['nullable', 'string', 'max:2000'],
            'branch' => ['nullable', 'string', 'max:120'],
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'guarantor_1_full_name' => ['nullable', 'string', 'max:200'],
            'guarantor_1_phone' => ['nullable', 'string', 'max:40'],
            'guarantor_1_id_number' => ['nullable', 'string', 'max:80'],
            'guarantor_1_relationship' => ['nullable', 'string', 'max:80'],
            'guarantor_1_address' => ['nullable', 'string', 'max:2000'],
            'guarantor_2_full_name' => ['nullable', 'string', 'max:200'],
            'guarantor_2_phone' => ['nullable', 'string', 'max:40'],
            'guarantor_2_id_number' => ['nullable', 'string', 'max:80'],
            'guarantor_2_relationship' => ['nullable', 'string', 'max:80'],
            'guarantor_2_address' => ['nullable', 'string', 'max:2000'],
        ];

        if ($kind === LoanClient::KIND_LEAD) {
            $rules['lead_status'] = ['nullable', 'string', 'max:40'];
            $rules['client_status'] = ['nullable', 'string', 'max:40'];
        } else {
            $rules['client_status'] = ['nullable', 'string', 'max:40'];
            $rules['lead_status'] = ['nullable', 'string', 'max:40'];
        }

        return $request->validate($rules);
    }

    /**
     * @return array<string, string>
     */
    private function handleClientImageUploads(Request $request, ?LoanClient $existing = null): array
    {
        $updates = [];
        $mapping = [
            'client_photo' => 'client_photo_path',
            'id_front_photo' => 'id_front_photo_path',
            'id_back_photo' => 'id_back_photo_path',
        ];

        foreach ($mapping as $fileField => $dbField) {
            if (! $request->hasFile($fileField)) {
                continue;
            }

            $file = $request->file($fileField);
            if (! $file) {
                continue;
            }

            $newPath = $file->store('loan-clients', 'public');
            $updates[$dbField] = $newPath;

            $oldPath = (string) ($existing?->{$dbField} ?? '');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        return $updates;
    }

    private function generateClientNumber(string $prefix = 'CL'): string
    {
        $prefix = strtoupper(trim($prefix));
        if ($prefix === '') {
            $prefix = 'CL';
        }

        $seed = ((int) LoanClient::query()->max('id')) + 1;
        $number = $prefix.'-'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT);

        while (LoanClient::query()->where('client_number', $number)->exists()) {
            $seed++;
            $number = $prefix.'-'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    /**
     * @return list<string>
     */
    private function branchOptions(): array
    {
        $fromDirectory = (Schema::hasTable('loan_branches')
            ? LoanBranch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->all()
            : []);

        $fromClients = LoanClient::query()
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch')
            ->all();

        $options = array_values(array_unique(array_filter(array_map(
            static fn ($name) => trim((string) $name),
            array_merge($fromDirectory, $fromClients)
        ))));
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private function syncBranchDirectory(mixed $branchName): ?string
    {
        $name = trim((string) ($branchName ?? ''));
        if ($name === '') {
            return null;
        }

        if (! Schema::hasTable('loan_branches') || ! Schema::hasTable('loan_regions')) {
            return $name;
        }

        $region = LoanRegion::query()->orderBy('name')->first();
        if (! $region) {
            $region = LoanRegion::query()->create([
                'name' => 'Default Region',
                'description' => 'Auto-created for quick branch setup.',
                'is_active' => true,
            ]);
        }

        LoanBranch::query()->firstOrCreate(
            ['name' => $name],
            [
                'loan_region_id' => $region->id,
                'is_active' => true,
            ]
        );

        return $name;
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeesForSelect()
    {
        return Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Keep ownership consistent with portfolio scoping on create.
     */
    private function resolveAssignedEmployeeForCreate(Request $request, mixed $requested): ?int
    {
        if ($this->canAccessAllLoanData($request->user())) {
            return $requested !== null && $requested !== '' ? (int) $requested : null;
        }

        $employeeId = $this->resolveLoanEmployeeId($request->user());

        return $employeeId ? (int) $employeeId : null;
    }

    /**
     * @return array<string, string>
     */
    private function clientBiodataLabels(): array
    {
        LoanFormFieldDefinition::ensureDefaults(LoanFormFieldDefinition::KIND_CLIENT_BIODATA);

        $definitions = LoanFormFieldDefinition::query()
            ->where('form_kind', LoanFormFieldDefinition::KIND_CLIENT_BIODATA)
            ->get();

        $defaults = [
            'phone' => 'Client Contact',
            'id_number' => 'Idno',
            'full_name' => 'Name',
            'gender' => 'Gender',
            'next_of_kin_contact' => 'Kin Contact',
            'next_of_kin_name' => 'Next Of Kin',
            'client_photo' => 'Client Photo',
            'assigned_employee_id' => 'Loan Officer',
            'id_back_photo' => 'Id Back Photo',
            'id_front_photo' => 'Id Front Photo',
        ];

        $aliases = [
            'phone' => ['client contact', 'phone', 'phone number', 'contact'],
            'id_number' => ['idno', 'national id / passport', 'id / registration', 'id number'],
            'full_name' => ['full name', 'name', 'client name'],
            'gender' => ['gender', 'sex'],
            'next_of_kin_contact' => ['kin contact', 'next of kin phone'],
            'next_of_kin_name' => ['next of kin', 'next of kin name'],
            'client_photo' => ['client photo', 'photo'],
            'assigned_employee_id' => ['loan officer', 'assigned officer'],
            'id_back_photo' => ['id back photo'],
            'id_front_photo' => ['id front photo', 'id document photo'],
        ];

        $labels = $defaults;
        foreach ($definitions as $field) {
            $normalized = strtolower(trim((string) $field->label));
            foreach ($aliases as $key => $options) {
                if (in_array($normalized, $options, true)) {
                    $labels[$key] = (string) $field->label;
                    break;
                }
            }
        }

        return $labels;
    }

    /**
     * @return array<int, array{key: string, label: string, data_type: string, select_options: array<int, string>}>
     */
    private function clientBiodataDynamicFields(): array
    {
        LoanFormFieldDefinition::ensureDefaults(LoanFormFieldDefinition::KIND_CLIENT_BIODATA);
        $definitions = LoanFormFieldDefinition::query()
            ->where('form_kind', LoanFormFieldDefinition::KIND_CLIENT_BIODATA)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $mappedLabels = collect([
            'name',
            'full name',
            'client name',
            'client contact',
            'phone',
            'phone number',
            'idno',
            'national id / passport',
            'id / registration',
            'id number',
            'gender',
            'sex',
            'kin contact',
            'next of kin phone',
            'next of kin',
            'next of kin name',
            'client photo',
            'photo',
            'loan officer',
            'assigned officer',
            'id back photo',
            'id front photo',
            'id document photo',
        ])->map(fn (string $v): string => strtolower(trim($v)))->all();

        return $definitions
            ->filter(function (LoanFormFieldDefinition $field) use ($mappedLabels): bool {
                $normalized = strtolower(trim((string) $field->label));
                return ! in_array($normalized, $mappedLabels, true);
            })
            ->map(function (LoanFormFieldDefinition $field): array {
                $options = collect(explode(',', (string) ($field->select_options ?? '')))
                    ->map(fn (string $opt): string => trim($opt))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'key' => (string) $field->field_key,
                    'label' => (string) $field->label,
                    'data_type' => (string) $field->data_type,
                    'select_options' => $options,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDynamicBiodataMeta(Request $request, ?LoanClient $existing = null): array
    {
        $existingMeta = (array) ($existing?->biodata_meta ?? []);
        $result = [];
        $definitions = $this->clientBiodataDynamicFields();
        foreach ($definitions as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $type = (string) ($field['data_type'] ?? LoanFormFieldDefinition::TYPE_ALPHANUMERIC);
            if ($type === LoanFormFieldDefinition::TYPE_IMAGE) {
                $inputKey = "biodata_files.$key";
                if ($request->hasFile($inputKey)) {
                    $file = $request->file($inputKey);
                    if ($file) {
                        $newPath = $file->store('loan-clients/biodata', 'public');
                        $result[$key] = $newPath;

                        $oldPath = (string) ($existingMeta[$key] ?? '');
                        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                        continue;
                    }
                }

                if (array_key_exists($key, $existingMeta)) {
                    $result[$key] = $existingMeta[$key];
                }
                continue;
            }

            $inputValue = $request->input("biodata_meta.$key");
            if (is_array($inputValue)) {
                $inputValue = '';
            }
            $result[$key] = trim((string) ($inputValue ?? ''));
        }

        return $result;
    }

}
