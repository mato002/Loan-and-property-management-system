<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\LoanBookApplication;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanBranch;
use App\Models\LoanClient;
use App\Models\LoanSystemSetting;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanBookLoansController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function index(Request $request)
    {
        $query = LoanBookLoan::query()
            ->with(['loanClient', 'application'])
            ->withSum(['payments as processed_paid_amount' => fn (Builder $builder) => $builder->processedQueue()], 'amount');
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $branch = trim((string) $request->query('branch', ''));
        $repayment = trim((string) $request->query('repayment', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 15)));

        $query
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('loan_number', 'like', '%'.$q.'%')
                        ->orWhere('product_name', 'like', '%'.$q.'%')
                        ->orWhere('checkoff_employer', 'like', '%'.$q.'%')
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
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch))
            ->when($repayment === 'fully_paid', fn (Builder $builder) => $builder->where('balance', '<=', 0.01))
            ->when($repayment === 'has_balance', fn (Builder $builder) => $builder->where('balance', '>', 0.01));

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('created_at')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-loans-'.now()->format('Ymd_His'),
                ['Loan #', 'Client #', 'Client Name', 'Product', 'Principal', 'Balance', 'Interest Rate', 'DPD', 'Status', 'Branch'],
                function () use ($rows) {
                    foreach ($rows as $loan) {
                        yield [
                            (string) $loan->loan_number,
                            (string) ($loan->loanClient->client_number ?? ''),
                            (string) ($loan->loanClient->full_name ?? ''),
                            (string) $loan->product_name,
                            number_format((float) $loan->principal, 2, '.', ''),
                            number_format((float) $loan->balance, 2, '.', ''),
                            number_format((float) $loan->interest_rate, 2, '.', ''),
                            (string) $loan->dpd,
                            (string) $loan->status,
                            (string) ($loan->branch ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $loans = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        $branches = LoanBookLoan::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch');

        return view('loan.book.loans.index', [
            'title' => 'View loans',
            'subtitle' => 'Active and closed facilities in LoanBook.',
            'loans' => $loans,
            'q' => $q,
            'status' => $status,
            'branch' => $branch,
            'repayment' => $repayment,
            'perPage' => $perPage,
            'statuses' => $this->statusOptions(),
            'branches' => $branches,
        ]);
    }

    public function arrears(Request $request)
    {
        $query = LoanBookLoan::query()->with(['loanClient']);
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $q = trim((string) $request->query('q', ''));
        $branch = trim((string) $request->query('branch', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));
        $query = $query
            ->where('status', LoanBookLoan::STATUS_ACTIVE)
            ->where('dpd', '>', 0)
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('loan_number', 'like', '%'.$q.'%')
                        ->orWhere('product_name', 'like', '%'.$q.'%')
                        ->orWhere('branch', 'like', '%'.$q.'%')
                        ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                            $client->where('client_number', 'like', '%'.$q.'%')
                                ->orWhere('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch));

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('dpd')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-arrears-'.now()->format('Ymd_His'),
                ['Loan #', 'Client #', 'Client Name', 'Balance', 'DPD', 'Branch', 'Status'],
                function () use ($rows) {
                    foreach ($rows as $loan) {
                        yield [
                            (string) $loan->loan_number,
                            (string) ($loan->loanClient->client_number ?? ''),
                            (string) ($loan->loanClient->full_name ?? ''),
                            number_format((float) $loan->balance, 2, '.', ''),
                            (string) $loan->dpd,
                            (string) ($loan->branch ?? ''),
                            (string) $loan->status,
                        ];
                    }
                },
                $export
            );
        }

        $loans = $query->orderByDesc('dpd')->paginate($perPage)->withQueryString();
        $branches = LoanBookLoan::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch');

        return view('loan.book.loans.arrears', [
            'title' => 'Loan arrears',
            'subtitle' => 'Active accounts with days past due.',
            'loans' => $loans,
            'q' => $q,
            'branch' => $branch,
            'perPage' => $perPage,
            'branches' => $branches,
        ]);
    }

    public function checkoff(Request $request)
    {
        $query = LoanBookLoan::query()->with(['loanClient']);
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $branch = trim((string) $request->query('branch', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $query = $query
            ->where('is_checkoff', true)
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('loan_number', 'like', '%'.$q.'%')
                        ->orWhere('checkoff_employer', 'like', '%'.$q.'%')
                        ->orWhere('branch', 'like', '%'.$q.'%')
                        ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                            $client->where('client_number', 'like', '%'.$q.'%')
                                ->orWhere('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch));

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('balance')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-checkoff-'.now()->format('Ymd_His'),
                ['Loan #', 'Client #', 'Client Name', 'Employer', 'Balance', 'Status', 'Branch'],
                function () use ($rows) {
                    foreach ($rows as $loan) {
                        yield [
                            (string) $loan->loan_number,
                            (string) ($loan->loanClient->client_number ?? ''),
                            (string) ($loan->loanClient->full_name ?? ''),
                            (string) ($loan->checkoff_employer ?? ''),
                            number_format((float) $loan->balance, 2, '.', ''),
                            (string) $loan->status,
                            (string) ($loan->branch ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $loans = $query->orderByDesc('balance')->paginate($perPage)->withQueryString();
        $branches = LoanBookLoan::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch');

        return view('loan.book.loans.checkoff', [
            'title' => 'Checkoff loans',
            'subtitle' => 'Salary-checkoff and employer-deduct facilities.',
            'loans' => $loans,
            'q' => $q,
            'status' => $status,
            'branch' => $branch,
            'perPage' => $perPage,
            'statuses' => $this->statusOptions(),
            'branches' => $branches,
        ]);
    }

    public function create(): View
    {
        return view('loan.book.loans.create', [
            'title' => 'Create loan',
            'subtitle' => 'Book a new facility (manual or from an approved application).',
            'clients' => LoanClient::query()
                ->clients()
                ->when(! $this->canAccessAllLoanData(auth()->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId(auth()->user())))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'applications' => LoanBookApplication::query()
                ->whereIn('stage', [LoanBookApplication::STAGE_APPROVED, LoanBookApplication::STAGE_DISBURSED])
                ->whereDoesntHave('loan')
                ->with('loanClient')
                ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user()))
                ->orderByDesc('created_at')
                ->get(),
            'statuses' => $this->statusOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedLoan($request);
        $validated['is_checkoff'] = $request->boolean('is_checkoff');
        if (empty($validated['loan_book_application_id'])) {
            $validated['loan_book_application_id'] = null;
        }
        $client = LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $this->applyDirectoryBranch($validated);
        if (empty($validated['branch'])) {
            $validated['branch'] = $client->branch;
        }

        $next = (LoanBookLoan::query()->max('id') ?? 0) + 1;
        $validated['loan_number'] = 'LN-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $this->initializeRepaymentBuckets($validated);

        $loan = LoanBookLoan::query()->create($validated);

        if ($loan->loan_book_application_id) {
            LoanBookApplication::query()
                ->whereKey($loan->loan_book_application_id)
                ->update(['stage' => LoanBookApplication::STAGE_APPROVED]);
        }

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan booked.'));
    }

    public function edit(LoanBookLoan $loan_book_loan): View
    {
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);

        return view('loan.book.loans.edit', [
            'title' => 'Edit loan',
            'subtitle' => $loan_book_loan->loan_number,
            'loan' => $loan_book_loan,
            'clients' => LoanClient::query()
                ->clients()
                ->when(! $this->canAccessAllLoanData(auth()->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId(auth()->user())))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'applications' => LoanBookApplication::query()
                ->with('loanClient')
                ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user()))
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'statuses' => $this->statusOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function show(LoanBookLoan $loan_book_loan): View
    {
        $loan_book_loan->load([
            'loanClient',
            'application',
            'disbursements' => fn ($query) => $query->orderByDesc('disbursed_at')->orderByDesc('id'),
            'payments' => fn ($query) => $query->processedQueue()->orderByDesc('transaction_at')->orderByDesc('id')->limit(50),
        ]);
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);

        $recentCollections = LoanBookCollectionEntry::query()
            ->whereHas('loan', function (Builder $query) use ($loan_book_loan): void {
                $query->where('loan_client_id', $loan_book_loan->loan_client_id);
            })
            ->orderByDesc('collected_on')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('loan.book.loans.show', [
            'title' => 'Loan details',
            'subtitle' => $loan_book_loan->loan_number,
            'loan' => $loan_book_loan,
            'recentCollections' => $recentCollections,
        ]);
    }

    public function rebuildSnapshot(Request $request, LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $loan_book_loan->load('loanClient');
        $this->ensureLoanClientOwner($loan_book_loan->loanClient, $request->user());

        DB::transaction(function () use ($loan_book_loan, $request): void {
            /** @var LoanBookLoan $loan */
            $loan = LoanBookLoan::query()->lockForUpdate()->findOrFail($loan_book_loan->id);

            $disbursedPrincipal = (float) $loan->disbursements()->sum('amount');
            if ($disbursedPrincipal <= 0.0) {
                $disbursedPrincipal = max(0.0, (float) $loan->principal);
            }

            $principalOutstanding = $disbursedPrincipal;
            $interestOutstanding = $this->estimateInterestOutstanding(
                $disbursedPrincipal,
                max(0.0, (float) $loan->interest_rate),
                $loan->disbursed_at,
                $loan->maturity_date
            );
            $feesOutstanding = max(0.0, (float) $loan->fees_outstanding);

            $payments = LoanBookPayment::query()
                ->where('loan_book_loan_id', $loan->id)
                ->processedQueue()
                ->orderBy('transaction_at')
                ->orderBy('id')
                ->get();

            foreach ($payments as $payment) {
                $delta = abs((float) $payment->amount);
                if ($delta <= 0.0) {
                    continue;
                }

                if ($payment->payment_kind === LoanBookPayment::KIND_C2B_REVERSAL) {
                    $principalOutstanding = round($principalOutstanding + $delta, 2);
                    continue;
                }

                $remaining = $delta;
                foreach ($this->repaymentOrder() as $bucket) {
                    if ($remaining <= 0.0) {
                        break;
                    }
                    if ($bucket === 'fees') {
                        $apply = min($remaining, max(0.0, $feesOutstanding));
                        $feesOutstanding = round($feesOutstanding - $apply, 2);
                        $remaining -= $apply;
                        continue;
                    }
                    if ($bucket === 'interest') {
                        $apply = min($remaining, max(0.0, $interestOutstanding));
                        $interestOutstanding = round($interestOutstanding - $apply, 2);
                        $remaining -= $apply;
                        continue;
                    }
                    if ($bucket === 'principal') {
                        $apply = min($remaining, max(0.0, $principalOutstanding));
                        $principalOutstanding = round($principalOutstanding - $apply, 2);
                        $remaining -= $apply;
                    }
                }
            }

            $balance = round(max(0.0, $principalOutstanding + $interestOutstanding + $feesOutstanding), 2);

            $audit = '[Snapshot rebuild '.now()->format('Y-m-d H:i').'] Recomputed from disbursements + processed payments by '
                .trim((string) ($request->user()?->name ?? 'System')).'.';
            $existingNotes = trim((string) ($loan->notes ?? ''));

            $loan->update([
                'principal' => $disbursedPrincipal,
                'principal_outstanding' => $principalOutstanding,
                'interest_outstanding' => $interestOutstanding,
                'fees_outstanding' => $feesOutstanding,
                'balance' => $balance,
                'status' => $balance <= 0.0 ? LoanBookLoan::STATUS_CLOSED : ($loan->status === LoanBookLoan::STATUS_PENDING_DISBURSEMENT ? LoanBookLoan::STATUS_ACTIVE : $loan->status),
                'notes' => $existingNotes !== '' ? $existingNotes."\n".$audit : $audit,
            ]);
        });

        return redirect()
            ->route('loan.book.loans.show', $loan_book_loan)
            ->with('status', 'Repayment snapshot rebuilt from disbursements and processed payments.');
    }

    public function update(Request $request, LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);

        $validated = $this->validatedLoan($request, false);
        $validated['is_checkoff'] = $request->boolean('is_checkoff');
        if (empty($validated['loan_book_application_id'])) {
            $validated['loan_book_application_id'] = null;
        }
        LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $loan_book_loan->update($validated);

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan updated.'));
    }

    public function destroy(LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);

        if ($loan_book_loan->disbursements()->exists() || $loan_book_loan->collectionEntries()->exists()) {
            return redirect()
                ->route('loan.book.loans.index')
                ->with('error', __('Remove disbursements and collection lines before deleting this loan.'));
        }

        $loan_book_loan->delete();

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan removed.'));
    }

    private function validatedLoan(Request $request, bool $isCreate = true): array
    {
        $rules = [
            'loan_book_application_id' => ['nullable', 'exists:loan_book_applications,id'],
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'principal' => ['required', 'numeric', 'min:0'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys($this->statusOptions()))],
            'dpd' => ['required', 'integer', 'min:0', 'max:9999'],
            'disbursed_at' => ['nullable', 'date'],
            'maturity_date' => ['nullable', 'date'],
            'checkoff_employer' => ['nullable', 'string', 'max:160'],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if (Schema::hasTable('loan_branches')) {
            $rules['loan_branch_id'] = ['nullable', 'exists:loan_branches,id'];
        }

        return $request->validate($rules);
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            LoanBookLoan::STATUS_PENDING_DISBURSEMENT => 'Pending disbursement',
            LoanBookLoan::STATUS_ACTIVE => 'Active',
            LoanBookLoan::STATUS_CLOSED => 'Closed',
            LoanBookLoan::STATUS_WRITTEN_OFF => 'Written off',
            LoanBookLoan::STATUS_RESTRUCTURED => 'Restructured',
        ];
    }

    private function branchOptions(): Collection
    {
        if (! Schema::hasTable('loan_branches')) {
            return collect();
        }

        return LoanBranch::query()->with('region')->orderBy('name')->get();
    }

    private function applyDirectoryBranch(array &$validated): void
    {
        if (! Schema::hasTable('loan_branches')) {
            unset($validated['loan_branch_id']);

            return;
        }

        if (empty($validated['loan_branch_id'])) {
            $validated['loan_branch_id'] = null;

            return;
        }

        $branch = LoanBranch::query()->find($validated['loan_branch_id']);
        if ($branch) {
            $validated['branch'] = $branch->name;
        }
    }

    private function initializeRepaymentBuckets(array &$validated): void
    {
        $principal = max(0.0, (float) ($validated['principal'] ?? 0));
        $interestRate = max(0.0, (float) ($validated['interest_rate'] ?? 0));
        $interestOutstanding = $this->estimateInterestOutstanding(
            $principal,
            $interestRate,
            $validated['disbursed_at'] ?? null,
            $validated['maturity_date'] ?? null
        );

        if (empty($validated['principal_outstanding'])) {
            $validated['principal_outstanding'] = $principal;
        }
        if (empty($validated['interest_outstanding'])) {
            $validated['interest_outstanding'] = $interestOutstanding;
        }
        if (! array_key_exists('fees_outstanding', $validated)) {
            $validated['fees_outstanding'] = 0;
        }
        if (empty($validated['balance'])) {
            $validated['balance'] = round(
                (float) $validated['principal_outstanding']
                + (float) $validated['interest_outstanding']
                + (float) $validated['fees_outstanding'],
                2
            );
        }
    }

    private function estimateInterestOutstanding(float $principal, float $annualRate, mixed $disbursedAt, mixed $maturityDate): float
    {
        if ($principal <= 0 || $annualRate <= 0) {
            return 0.0;
        }

        $months = 12;
        if (filled($disbursedAt) && filled($maturityDate)) {
            try {
                $from = \Illuminate\Support\Carbon::parse($disbursedAt)->startOfDay();
                $to = \Illuminate\Support\Carbon::parse($maturityDate)->startOfDay();
                $months = max(1, $from->diffInMonths($to));
            } catch (\Throwable $e) {
                $months = 12;
            }
        }

        return round($principal * ($annualRate / 100) * ($months / 12), 2);
    }

    /**
     * @return list<'fees'|'interest'|'principal'>
     */
    private function repaymentOrder(): array
    {
        $raw = (string) (LoanSystemSetting::getValue('loan_repayment_allocation_order', 'fees,interest,principal') ?? '');
        $parts = array_values(array_filter(array_map(
            static fn (string $p) => strtolower(trim($p)),
            explode(',', $raw)
        )));
        $valid = ['fees', 'interest', 'principal'];
        $order = array_values(array_intersect($parts, $valid));
        foreach ($valid as $v) {
            if (! in_array($v, $order, true)) {
                $order[] = $v;
            }
        }

        return $order;
    }

}
