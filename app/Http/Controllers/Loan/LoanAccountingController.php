<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPettyCashEntry;
use App\Models\AccountingPostingRule;
use App\Models\AccountingRequisition;
use App\Models\AccountingSalaryAdvance;
use App\Models\AccountingUtilityPayment;
use App\Models\AccountingWalletSlotSetting;
use App\Models\Employee;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Carbon\Carbon;

class LoanAccountingController extends Controller
{
    private function assignRequisitionReference(AccountingRequisition $row): void
    {
        $row->update([
            'reference' => 'REQ-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
        ]);
    }

    /* ---------- Books hub ---------- */

    public function books(): View
    {
        return view('loan.accounting.books');
    }

    /* ---------- Chart of accounts ---------- */

    /** @return array<string, string> */
    private function walletSlotLabels(): array
    {
        return [
            'savings_account' => 'Savings Account',
            'transactional_account' => 'Transactional Account',
            'investment_account' => 'Investment Account',
            'investors_roi_account' => 'Investors ROI Account',
            'cash_account' => 'Cash Account',
            'withdrawals_suspense_account' => 'Withdrawals Suspense Account',
        ];
    }

    public function chartIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $export = request()->string('export')->toString();
        $q = request()->string('q')->toString();
        $active = request()->string('active')->toString(); // '', '1', '0'

        $accountsQuery = AccountingChartAccount::query();
        if ($active === '1') {
            $accountsQuery->where('is_active', true);
        } elseif ($active === '0') {
            $accountsQuery->where('is_active', false);
        }
        if ($q !== '') {
            $accountsQuery->where(function ($qq) use ($q) {
                $qq->where('code', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%');
            });
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('chart-of-accounts', [
                'Code', 'Name', 'Type', 'Active', 'Cash Account',
            ], function () use ($accountsQuery) {
                return $accountsQuery->orderBy('code')->get()->map(function (AccountingChartAccount $a) {
                    return [
                        (string) ($a->code ?? ''),
                        (string) ($a->name ?? ''),
                        (string) ($a->account_type ?? ''),
                        $a->is_active ? 'yes' : 'no',
                        $a->is_cash_account ? 'yes' : 'no',
                    ];
                });
            }, $export);
        }

        $accounts = $accountsQuery->orderBy('code')->get();

        $accountsByType = $accounts->groupBy('account_type');

        $selectAccounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $slotLabels = $this->walletSlotLabels();
        $slotSettings = AccountingWalletSlotSetting::query()
            ->whereIn('slot_key', array_keys($slotLabels))
            ->get()
            ->keyBy('slot_key');

        $walletSlots = collect($slotLabels)->map(function (string $label, string $key) use ($slotSettings) {
            $row = $slotSettings->get($key);

            return [
                'key' => $key,
                'label' => $label,
                'setting_id' => $row?->id,
                'account_id' => $row?->accounting_chart_account_id,
            ];
        });

        $postingRules = AccountingPostingRule::query()
            ->with(['debitAccount', 'creditAccount'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $chartOverview = $this->buildChartOfAccountsOverview($accounts, $walletSlots, $postingRules);

        return view('loan.accounting.chart.index', compact(
            'accounts',
            'accountsByType',
            'selectAccounts',
            'walletSlots',
            'postingRules',
            'chartOverview',
            'q',
            'active'
        ));
    }

    /**
     * @param  Collection<int, AccountingChartAccount>  $accounts
     * @param  Collection<string, array<string, mixed>>  $walletSlots
     * @param  Collection<int, AccountingPostingRule>  $postingRules
     * @return array<string, mixed>
     */
    private function buildChartOfAccountsOverview($accounts, $walletSlots, $postingRules): array
    {
        $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
        $byType = [];
        foreach ($typeOrder as $t) {
            $byType[$t] = 0;
        }
        foreach ($accounts as $a) {
            $t = (string) $a->account_type;
            if (array_key_exists($t, $byType)) {
                $byType[$t]++;
            }
        }

        $active = $accounts->where('is_active', true)->count();
        $total = $accounts->count();
        $walletTotal = $walletSlots->count();
        $walletFilled = $walletSlots->filter(fn (array $s): bool => ! empty($s['account_id']))->count();
        $rulesTotal = $postingRules->count();
        $rulesMapped = $postingRules->filter(function (AccountingPostingRule $r): bool {
            return $r->debit_account_id !== null && $r->credit_account_id !== null;
        })->count();

        $journal30 = 0;
        if (Schema::hasTable('accounting_journal_entries')) {
            $journal30 = AccountingJournalEntry::query()
                ->where('entry_date', '>=', now()->subDays(30)->toDateString())
                ->count();
        }

        $typeLabels = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
        $typeValues = array_map(fn (string $t): int => $byType[$t] ?? 0, $typeOrder);

        return [
            'accounts_total' => $total,
            'accounts_active' => $active,
            'accounts_inactive' => max(0, $total - $active),
            'cash_accounts' => $accounts->where('is_cash_account', true)->count(),
            'wallet_filled' => $walletFilled,
            'wallet_total' => $walletTotal,
            'wallet_pct' => $walletTotal > 0 ? round(100 * $walletFilled / $walletTotal) : 0,
            'rules_mapped' => $rulesMapped,
            'rules_total' => $rulesTotal,
            'rules_pct' => $rulesTotal > 0 ? round(100 * $rulesMapped / $rulesTotal) : 0,
            'journal_entries_30d' => $journal30,
            'type_chart' => [
                'labels' => $typeLabels,
                'values' => $typeValues,
            ],
        ];
    }

    public function chartWalletSlotsUpdate(Request $request): RedirectResponse
    {
        $keys = array_keys($this->walletSlotLabels());
        $rules = [];
        foreach ($keys as $key) {
            $rules['slots.'.$key] = ['nullable', 'integer', 'exists:accounting_chart_accounts,id'];
        }
        $validated = $request->validate($rules);

        $slots = $validated['slots'] ?? [];
        foreach ($keys as $key) {
            AccountingWalletSlotSetting::query()->updateOrCreate(
                ['slot_key' => $key],
                ['accounting_chart_account_id' => $slots[$key] ?? null]
            );
        }

        return redirect()->route('loan.accounting.chart.index')->with('status', 'Wallet account mappings updated.');
    }

    public function chartPostingRuleUpdate(Request $request, AccountingPostingRule $accounting_posting_rule): RedirectResponse
    {
        if (! $accounting_posting_rule->is_editable) {
            abort(403);
        }

        $validated = $request->validate([
            'debit_account_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
            'credit_account_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
        ]);

        if ($accounting_posting_rule->rule_key === \App\Services\LoanBookGlPostingService::RULE_LOAN_LEDGER
            && empty($validated['credit_account_id'])) {
            return back()->withErrors([
                'credit_account_id' => 'Loan Ledger requires a credit account (loan portfolio / receivable).',
            ]);
        }

        $accounting_posting_rule->update([
            'debit_account_id' => $validated['debit_account_id'] ?? null,
            'credit_account_id' => $validated['credit_account_id'] ?? null,
        ]);

        return redirect()->route('loan.accounting.chart.index')->with('status', 'Accounting rule updated.');
    }

    public function chartCreate(): View
    {
        return view('loan.accounting.chart.create');
    }

    public function chartStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:accounting_chart_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:asset,liability,equity,income,expense'],
            'is_cash_account' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        AccountingChartAccount::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'is_cash_account' => $request->boolean('is_cash_account'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('loan.accounting.chart.index')->with('status', 'Account created.');
    }

    public function chartEdit(AccountingChartAccount $accounting_chart_account): View
    {
        return view('loan.accounting.chart.edit', ['account' => $accounting_chart_account]);
    }

    public function chartUpdate(Request $request, AccountingChartAccount $accounting_chart_account): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:accounting_chart_accounts,code,'.$accounting_chart_account->id],
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:asset,liability,equity,income,expense'],
            'is_cash_account' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $accounting_chart_account->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'is_cash_account' => $request->boolean('is_cash_account'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('loan.accounting.chart.index')->with('status', 'Account updated.');
    }

    public function chartDestroy(AccountingChartAccount $accounting_chart_account): RedirectResponse
    {
        if (AccountingJournalLine::query()->where('accounting_chart_account_id', $accounting_chart_account->id)->exists()) {
            return redirect()->back()->withErrors(['delete' => 'This account has journal history and cannot be deleted.']);
        }

        $accounting_chart_account->delete();

        return redirect()->route('loan.accounting.chart.index')->with('status', 'Account removed.');
    }

    /* ---------- Journal entries ---------- */

    public function journalIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $reference = request()->string('reference')->toString();
        $createdBy = request()->integer('created_by');
        $export = request()->string('export')->toString();

        $q = AccountingJournalEntry::query()
            ->with('createdByUser')
            ->withCount('lines');

        if ($from !== '') {
            $q->where('entry_date', '>=', $from);
        }
        if ($to !== '') {
            $q->where('entry_date', '<=', $to);
        }
        if ($reference !== '') {
            $q->where('reference', 'like', '%'.$reference.'%');
        }
        if ($createdBy) {
            $q->where('created_by', $createdBy);
        }

        $q->orderByDesc('entry_date')->orderByDesc('id');

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('journal-entries', [
                'Entry Date', 'Reference', 'Description', 'Lines', 'Created By',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingJournalEntry $e) {
                    return [
                        optional($e->entry_date)->format('Y-m-d'),
                        (string) ($e->reference ?? ''),
                        (string) ($e->description ?? ''),
                        (int) ($e->lines_count ?? 0),
                        (string) ($e->createdByUser?->name ?? ''),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $entries = $q->paginate($perPage)->withQueryString();

        return view('loan.accounting.journal.index', compact('entries', 'from', 'to', 'reference', 'createdBy', 'perPage'));
    }

    public function journalCreate(): View
    {
        $accounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('loan.accounting.journal.create', compact('accounts'));
    }

    public function journalStore(Request $request): RedirectResponse
    {
        $compactLines = collect($request->input('lines', []))
            ->filter(fn (array $l) => ! empty($l['accounting_chart_account_id']))
            ->values()
            ->all();
        $request->merge(['lines' => $compactLines]);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.accounting_chart_account_id' => ['required', 'exists:accounting_chart_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:500'],
        ]);

        $lines = collect($validated['lines']);
        foreach ($lines as $i => $line) {
            $d = round((float) ($line['debit'] ?? 0), 2);
            $c = round((float) ($line['credit'] ?? 0), 2);
            if ($d > 0 && $c > 0) {
                // allow a single-line "self-balanced" entry (or any line) only when debit equals credit
                if ($d !== $c) {
                    return back()->withErrors(['lines.'.$i => 'Each line must be either a debit or a credit (or have equal debit and credit).'])->withInput();
                }
            }
            if ($d <= 0 && $c <= 0) {
                return back()->withErrors(['lines.'.$i => 'Each line needs a debit or credit amount.'])->withInput();
            }
        }

        $totalDebit = round($lines->sum(fn ($l) => (float) ($l['debit'] ?? 0)), 2);
        $totalCredit = round($lines->sum(fn ($l) => (float) ($l['credit'] ?? 0)), 2);
        if ($totalDebit !== $totalCredit || $totalDebit <= 0) {
            return back()->withErrors(['lines' => 'Total debits must equal total credits and be greater than zero.'])->withInput();
        }

        DB::transaction(function () use ($validated, $request, $lines) {
            $entry = AccountingJournalEntry::create([
                'entry_date' => $validated['entry_date'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            foreach ($lines as $line) {
                AccountingJournalLine::create([
                    'accounting_journal_entry_id' => $entry->id,
                    'accounting_chart_account_id' => $line['accounting_chart_account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'memo' => $line['memo'] ?? null,
                ]);
            }
        });

        return redirect()->route('loan.accounting.journal.index')->with('status', 'Journal entry posted.');
    }

    public function journalShow(AccountingJournalEntry $accounting_journal_entry): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $accounting_journal_entry->load(['lines.account', 'createdByUser']);

        $export = request()->string('export')->toString();
        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('journal-entry-'.$accounting_journal_entry->id, [
                'Account Code', 'Account Name', 'Debit', 'Credit', 'Memo',
            ], function () use ($accounting_journal_entry) {
                return $accounting_journal_entry->lines->map(function (AccountingJournalLine $line) {
                    return [
                        (string) ($line->account?->code ?? ''),
                        (string) ($line->account?->name ?? ''),
                        (string) ($line->debit ?? ''),
                        (string) ($line->credit ?? ''),
                        (string) ($line->memo ?? ''),
                    ];
                });
            }, $export);
        }

        return view('loan.accounting.journal.show', ['entry' => $accounting_journal_entry]);
    }

    public function journalDestroy(AccountingJournalEntry $accounting_journal_entry): RedirectResponse
    {
        $accounting_journal_entry->delete();

        return redirect()->route('loan.accounting.journal.index')->with('status', 'Journal entry deleted.');
    }
    
    public function journalBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one entry.']);
        }
        if ($action === 'delete') {
            AccountingJournalEntry::query()->whereIn('id', $ids)->delete();
            return back()->with('status', 'Selected journal entries deleted.');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    /* ---------- Ledger ---------- */

    public function ledger(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $export = $request->string('export')->toString();
        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('code')->get();

        $accountId = $request->integer('account_id');
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();

        $lines = collect();
        $account = null;
        $opening = 0.0;
        $closing = 0.0;

        if ($accountId) {
            $account = AccountingChartAccount::query()->find($accountId);
            if ($account) {
                $base = AccountingJournalLine::query()
                    ->where('accounting_chart_account_id', $account->id)
                    ->whereHas('entry', function ($q) use ($from) {
                        $q->where('entry_date', '<', $from);
                    });
                $opening = (float) $base->sum('debit') - (float) $base->sum('credit');

                $lines = AccountingJournalLine::query()
                    ->where('accounting_chart_account_id', $account->id)
                    ->whereHas('entry', function ($q) use ($from, $to) {
                        $q->whereBetween('entry_date', [$from, $to]);
                    })
                    ->with(['entry'])
                    ->get()
                    ->sortBy(function (AccountingJournalLine $line) {
                        return [
                            $line->entry->entry_date->format('Y-m-d'),
                            $line->entry->id,
                            $line->id,
                        ];
                    })
                    ->values();

                $period = (float) $lines->sum('debit') - (float) $lines->sum('credit');
                $closing = $opening + $period;
            }
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true) && $account) {
            return TabularExport::stream('ledger-'.$account->code.'-'.$from.'-to-'.$to, [
                'Account Code', 'Account Name', 'From', 'To', 'Opening', 'Closing',
                'Entry Date', 'Journal ID', 'Reference', 'Debit', 'Credit', 'Memo',
            ], function () use ($account, $from, $to, $opening, $closing, $lines) {
                $headerRow = [[
                    (string) ($account->code ?? ''),
                    (string) ($account->name ?? ''),
                    (string) $from,
                    (string) $to,
                    (string) number_format((float) $opening, 2, '.', ''),
                    (string) number_format((float) $closing, 2, '.', ''),
                    '', '', '', '', '', '',
                ]];

                $detail = $lines->map(function (AccountingJournalLine $line) {
                    return [
                        '', '', '', '', '', '',
                        optional($line->entry?->entry_date)->format('Y-m-d'),
                        (string) ($line->entry?->id ?? ''),
                        (string) ($line->entry?->reference ?? ''),
                        (string) ($line->debit ?? ''),
                        (string) ($line->credit ?? ''),
                        (string) ($line->memo ?? ''),
                    ];
                });

                return collect($headerRow)->concat($detail);
            }, $export);
        }

        return view('loan.accounting.ledger', compact('accounts', 'account', 'lines', 'from', 'to', 'opening', 'closing'));
    }

    /* ---------- Requisitions ---------- */

    public function requisitionsIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = request()->string('status')->toString();
        $month = request()->string('month')->toString(); // YYYY-MM
        $export = request()->string('export')->toString();

        $baseQuery = AccountingRequisition::query()
            ->with(['requestedByUser', 'approvedByUser']);

        if ($status !== '' && in_array($status, [
            AccountingRequisition::STATUS_PENDING,
            AccountingRequisition::STATUS_APPROVED,
            AccountingRequisition::STATUS_REJECTED,
            AccountingRequisition::STATUS_PAID,
        ], true)) {
            $baseQuery->where('status', $status);
        }

        if ($month !== '') {
            try {
                $m = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $baseQuery->whereBetween('created_at', [$m, $m->copy()->endOfMonth()]);
            } catch (\Throwable $e) {
                // ignore invalid month filter
            }
        }

        $ordered = (clone $baseQuery)->orderByDesc('created_at');

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('requisitions', [
                'Reference', 'Title', 'Purpose', 'Amount', 'Currency', 'Status', 'Requested By', 'Approved By', 'Created At',
            ], function () use ($ordered) {
                return $ordered->get()->map(function (AccountingRequisition $r) {
                    return [
                        (string) ($r->reference ?? ''),
                        (string) ($r->title ?? ''),
                        (string) ($r->purpose ?? ''),
                        (string) $r->amount,
                        (string) ($r->currency ?? ''),
                        (string) ($r->status ?? ''),
                        (string) ($r->requestedByUser?->name ?? ''),
                        (string) ($r->approvedByUser?->name ?? ''),
                        optional($r->created_at)->toDateTimeString(),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $rows = $ordered->paginate($perPage)->withQueryString();

        $availableMonths = AccountingRequisition::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->pluck('ym')
            ->values();

        $statusOptions = [
            '' => 'All',
            AccountingRequisition::STATUS_PENDING => 'Pending',
            AccountingRequisition::STATUS_APPROVED => 'Approved',
            AccountingRequisition::STATUS_REJECTED => 'Rejected',
            AccountingRequisition::STATUS_PAID => 'Paid',
        ];

        return view('loan.accounting.requisitions.index', compact('rows', 'availableMonths', 'statusOptions', 'status', 'month', 'perPage'));
    }

    public function requisitionsCreate(): View
    {
        return view('loan.accounting.requisitions.create');
    }

    public function requisitionsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = AccountingRequisition::create([
            'reference' => null,
            'title' => $validated['title'],
            'purpose' => $validated['purpose'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'KES',
            'status' => AccountingRequisition::STATUS_PENDING,
            'requested_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
        ]);
        $this->assignRequisitionReference($row);

        return redirect()->route('loan.accounting.requisitions.index')->with('status', 'Requisition '.$row->reference.' submitted.');
    }

    public function requisitionsEdit(AccountingRequisition $accounting_requisition): View
    {
        return view('loan.accounting.requisitions.edit', ['row' => $accounting_requisition]);
    }

    public function requisitionsUpdate(Request $request, AccountingRequisition $accounting_requisition): RedirectResponse
    {
        if (in_array($accounting_requisition->status, [AccountingRequisition::STATUS_PAID, AccountingRequisition::STATUS_REJECTED], true)) {
            return redirect()->route('loan.accounting.requisitions.index')->withErrors(['status' => 'This requisition cannot be edited.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $accounting_requisition->update([
            'title' => $validated['title'],
            'purpose' => $validated['purpose'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'KES',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('loan.accounting.requisitions.index')->with('status', 'Requisition updated.');
    }

    public function requisitionsDestroy(AccountingRequisition $accounting_requisition): RedirectResponse
    {
        if ($accounting_requisition->status === AccountingRequisition::STATUS_PAID) {
            return redirect()->back()->withErrors(['delete' => 'Paid requisitions cannot be deleted.']);
        }
        $accounting_requisition->delete();

        return redirect()->route('loan.accounting.requisitions.index')->with('status', 'Requisition removed.');
    }
    
    public function requisitionsBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one requisition.']);
        }
        if ($action === 'delete') {
            // Do not delete PAID requisitions
            AccountingRequisition::query()
                ->whereIn('id', $ids)
                ->where('status', '!=', AccountingRequisition::STATUS_PAID)
                ->delete();
            return back()->with('status', 'Selected requisitions removed (excluding paid).');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function requisitionsApprove(Request $request, AccountingRequisition $accounting_requisition): RedirectResponse
    {
        abort_unless($accounting_requisition->status === AccountingRequisition::STATUS_PENDING, 403);
        $accounting_requisition->update([
            'status' => AccountingRequisition::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Requisition approved.');
    }

    public function requisitionsReject(AccountingRequisition $accounting_requisition): RedirectResponse
    {
        abort_unless($accounting_requisition->status === AccountingRequisition::STATUS_PENDING, 403);
        $accounting_requisition->update([
            'status' => AccountingRequisition::STATUS_REJECTED,
        ]);

        return redirect()->back()->with('status', 'Requisition rejected.');
    }

    public function requisitionsPay(AccountingRequisition $accounting_requisition): RedirectResponse
    {
        abort_unless($accounting_requisition->status === AccountingRequisition::STATUS_APPROVED, 403);
        $accounting_requisition->update([
            'status' => AccountingRequisition::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Marked as paid.');
    }

    /* ---------- Utility payments ---------- */

    public function utilitiesIndex(): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $type = request()->string('utility_type')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $provider = request()->string('provider')->toString();
        $method = request()->string('payment_method')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingUtilityPayment::query()
            ->with('recordedByUser')
            ->orderByDesc('paid_on')
            ->orderByDesc('id');

        if ($type !== '') {
            $q->where('utility_type', $type);
        }
        if ($provider !== '') {
            $q->where('provider', 'like', '%'.$provider.'%');
        }
        if ($method !== '') {
            $q->where('payment_method', $method);
        }
        if ($from !== '') {
            $q->where('paid_on', '>=', $from);
        }
        if ($to !== '') {
            $q->where('paid_on', '<=', $to);
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('utility-payments', [
                'Type', 'Provider', 'Bill Ref', 'Paid On', 'Amount', 'Currency', 'Method', 'Reference', 'Recorded By', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingUtilityPayment $r) {
                    return [
                        (string) ($r->utility_type ?? ''),
                        (string) ($r->provider ?? ''),
                        (string) ($r->bill_account_ref ?? ''),
                        optional($r->paid_on)->format('Y-m-d'),
                        (string) $r->amount,
                        (string) ($r->currency ?? ''),
                        (string) ($r->payment_method ?? ''),
                        (string) ($r->reference ?? ''),
                        (string) ($r->recordedByUser?->name ?? ''),
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $rows = $q->paginate($perPage)->withQueryString();

        $utilityTypes = AccountingUtilityPayment::query()
            ->select('utility_type')
            ->distinct()
            ->orderBy('utility_type')
            ->pluck('utility_type')
            ->values();

        $paymentMethods = AccountingUtilityPayment::query()
            ->select('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method')
            ->values();

        return view('loan.accounting.utilities.index', compact('rows', 'type', 'from', 'to', 'provider', 'method', 'utilityTypes', 'paymentMethods', 'perPage'));
    }

    public function utilitiesCreate(): View
    {
        return view('loan.accounting.utilities.create');
    }

    public function utilitiesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'utility_type' => ['required', 'string', 'max:64'],
            'provider' => ['nullable', 'string', 'max:255'],
            'bill_account_ref' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'paid_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        AccountingUtilityPayment::create([
            ...$validated,
            'currency' => $validated['currency'] ?? 'KES',
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('loan.accounting.utilities.index')->with('status', 'Utility payment recorded.');
    }

    public function utilitiesEdit(AccountingUtilityPayment $accounting_utility_payment): View
    {
        return view('loan.accounting.utilities.edit', ['row' => $accounting_utility_payment]);
    }

    public function utilitiesUpdate(Request $request, AccountingUtilityPayment $accounting_utility_payment): RedirectResponse
    {
        $validated = $request->validate([
            'utility_type' => ['required', 'string', 'max:64'],
            'provider' => ['nullable', 'string', 'max:255'],
            'bill_account_ref' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'paid_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $accounting_utility_payment->update($validated);

        return redirect()->route('loan.accounting.utilities.index')->with('status', 'Utility payment updated.');
    }

    public function utilitiesDestroy(AccountingUtilityPayment $accounting_utility_payment): RedirectResponse
    {
        $accounting_utility_payment->delete();

        return redirect()->route('loan.accounting.utilities.index')->with('status', 'Record removed.');
    }
    
    public function utilitiesBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one utility payment.']);
        }
        if ($action === 'delete') {
            AccountingUtilityPayment::query()->whereIn('id', $ids)->delete();
            return back()->with('status', 'Selected utility payments removed.');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    /* ---------- Petty cash ---------- */

    public function pettyIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $kind = request()->string('kind')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $search = request()->string('q')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingPettyCashEntry::query()
            ->with('recordedByUser')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ;

        if ($kind !== '' && in_array($kind, [AccountingPettyCashEntry::KIND_RECEIPT, AccountingPettyCashEntry::KIND_DISBURSEMENT], true)) {
            $q->where('kind', $kind);
        }
        if ($from !== '') {
            $q->where('entry_date', '>=', $from);
        }
        if ($to !== '') {
            $q->where('entry_date', '<=', $to);
        }
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('payee_or_source', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('petty-cash', [
                'Entry Date', 'Kind', 'Amount', 'Payee/Source', 'Description', 'Recorded By',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingPettyCashEntry $r) {
                    return [
                        optional($r->entry_date)->format('Y-m-d'),
                        (string) ($r->kind ?? ''),
                        (string) $r->amount,
                        (string) ($r->payee_or_source ?? ''),
                        (string) ($r->description ?? ''),
                        (string) ($r->recordedByUser?->name ?? ''),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 25)));
        $rows = $q->paginate($perPage)->withQueryString();

        $balance = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_RECEIPT)
            ->sum('amount')
            - (float) AccountingPettyCashEntry::query()
                ->where('kind', AccountingPettyCashEntry::KIND_DISBURSEMENT)
                ->sum('amount');

        return view('loan.accounting.petty.index', compact('rows', 'balance', 'kind', 'from', 'to', 'search', 'perPage'));
    }

    public function pettyCreate(): View
    {
        return view('loan.accounting.petty.create');
    }

    public function pettyStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'kind' => ['required', 'in:receipt,disbursement'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payee_or_source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        AccountingPettyCashEntry::create([
            ...$validated,
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('loan.accounting.petty.index')->with('status', 'Petty cash line saved.');
    }

    public function pettyEdit(AccountingPettyCashEntry $accounting_petty_cash_entry): View
    {
        return view('loan.accounting.petty.edit', ['row' => $accounting_petty_cash_entry]);
    }

    public function pettyUpdate(Request $request, AccountingPettyCashEntry $accounting_petty_cash_entry): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'kind' => ['required', 'in:receipt,disbursement'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payee_or_source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $accounting_petty_cash_entry->update($validated);

        return redirect()->route('loan.accounting.petty.index')->with('status', 'Entry updated.');
    }

    public function pettyDestroy(AccountingPettyCashEntry $accounting_petty_cash_entry): RedirectResponse
    {
        $accounting_petty_cash_entry->delete();

        return redirect()->route('loan.accounting.petty.index')->with('status', 'Entry removed.');
    }
    
    public function pettyBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one petty cash entry.']);
        }
        if ($action === 'delete') {
            AccountingPettyCashEntry::query()->whereIn('id', $ids)->delete();
            return back()->with('status', 'Selected petty cash entries removed.');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    /* ---------- Salary advances ---------- */

    public function advancesIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = request()->string('status')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $employeeId = request()->integer('employee_id');
        $export = request()->string('export')->toString();

        $q = AccountingSalaryAdvance::query()
            ->with(['employee', 'approvedByUser'])
            ->orderByDesc('requested_on')
            ->orderByDesc('id');

        if ($status !== '' && in_array($status, [
            AccountingSalaryAdvance::STATUS_PENDING,
            AccountingSalaryAdvance::STATUS_APPROVED,
            AccountingSalaryAdvance::STATUS_REJECTED,
            AccountingSalaryAdvance::STATUS_SETTLED,
        ], true)) {
            $q->where('status', $status);
        }
        if ($from !== '') {
            $q->where('requested_on', '>=', $from);
        }
        if ($to !== '') {
            $q->where('requested_on', '<=', $to);
        }
        if ($employeeId) {
            $q->where('employee_id', $employeeId);
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('salary-advances', [
                'Employee', 'Employee No', 'Requested On', 'Amount', 'Currency', 'Status', 'Approved By', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingSalaryAdvance $r) {
                    return [
                        (string) ($r->employee?->full_name ?? ''),
                        (string) ($r->employee?->employee_number ?? ''),
                        optional($r->requested_on)->format('Y-m-d'),
                        (string) $r->amount,
                        (string) ($r->currency ?? ''),
                        (string) ($r->status ?? ''),
                        (string) ($r->approvedByUser?->name ?? ''),
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $rows = $q->paginate($perPage)->withQueryString();

        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'employee_number']);

        return view('loan.accounting.advances.index', compact('rows', 'status', 'from', 'to', 'employeeId', 'employees', 'perPage'));
    }

    public function advancesCreate(): View
    {
        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get();

        return view('loan.accounting.advances.create', compact('employees'));
    }

    public function advancesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'requested_on' => ['required', 'date'],
            'reason_for_request' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        AccountingSalaryAdvance::create([
            ...$validated,
            'currency' => $validated['currency'] ?? 'KES',
            'status' => AccountingSalaryAdvance::STATUS_PENDING,
        ]);

        return redirect()->route('loan.accounting.advances.index')->with('status', 'Salary advance request recorded.');
    }

    public function advancesEdit(AccountingSalaryAdvance $accounting_salary_advance): View
    {
        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get();

        return view('loan.accounting.advances.edit', ['row' => $accounting_salary_advance, 'employees' => $employees]);
    }

    public function advancesUpdate(Request $request, AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        if (in_array($accounting_salary_advance->status, [AccountingSalaryAdvance::STATUS_SETTLED], true)) {
            return redirect()->route('loan.accounting.advances.index')->withErrors(['status' => 'Settled advances cannot be edited.']);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'requested_on' => ['required', 'date'],
            'reason_for_request' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $accounting_salary_advance->update($validated);

        return redirect()->route('loan.accounting.advances.index')->with('status', 'Advance updated.');
    }

    public function advancesDestroy(AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        if ($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_SETTLED) {
            return redirect()->back()->withErrors(['delete' => 'Settled advances cannot be deleted.']);
        }
        $accounting_salary_advance->delete();

        return redirect()->route('loan.accounting.advances.index')->with('status', 'Advance removed.');
    }
    
    public function advancesBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one advance.']);
        }
        if ($action === 'delete') {
            AccountingSalaryAdvance::query()
                ->whereIn('id', $ids)
                ->where('status', '!=', AccountingSalaryAdvance::STATUS_SETTLED)
                ->delete();
            return back()->with('status', 'Selected advances removed (excluding settled).');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function advancesApprove(Request $request, AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        abort_unless($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_PENDING, 403);
        $validated = $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        $accounting_salary_advance->update([
            'status' => AccountingSalaryAdvance::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_amount' => $validated['approved_amount'] ?? $accounting_salary_advance->amount,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Advance approved.');
    }

    public function advancesReject(AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        abort_unless($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_PENDING, 403);
        $accounting_salary_advance->update([
            'status' => AccountingSalaryAdvance::STATUS_REJECTED,
        ]);

        return redirect()->back()->with('status', 'Advance rejected.');
    }

    public function advancesSettle(AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        abort_unless($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_APPROVED, 403);
        $accounting_salary_advance->update([
            'status' => AccountingSalaryAdvance::STATUS_SETTLED,
            'settled_on' => now()->toDateString(),
        ]);

        return redirect()->back()->with('status', 'Advance marked settled.');
    }

    /* ---------- Reports ---------- */

    public function expenseSummary(Request $request): View
    {
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();

        $utilities = (float) AccountingUtilityPayment::query()
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        $pettyOut = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_DISBURSEMENT)
            ->whereBetween('entry_date', [$from, $to])
            ->sum('amount');

        $requisitionsPaid = (float) AccountingRequisition::query()
            ->where('status', AccountingRequisition::STATUS_PAID)
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->sum('amount');

        $advances = (float) AccountingSalaryAdvance::query()
            ->whereIn('status', [AccountingSalaryAdvance::STATUS_APPROVED, AccountingSalaryAdvance::STATUS_SETTLED])
            ->whereBetween('requested_on', [$from, $to])
            ->sum('amount');

        $expenseAccountIds = AccountingChartAccount::query()
            ->where('account_type', AccountingChartAccount::TYPE_EXPENSE)
            ->pluck('id');

        $jel = AccountingJournalLine::query()
            ->whereIn('accounting_chart_account_id', $expenseAccountIds)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->whereBetween('entry_date', [$from, $to]);
            });
        $journalExpense = (float) (clone $jel)->sum('debit') - (float) (clone $jel)->sum('credit');

        $total = $utilities + $pettyOut + $requisitionsPaid + $advances + $journalExpense;

        return view('loan.accounting.expense-summary', compact(
            'from',
            'to',
            'utilities',
            'pettyOut',
            'requisitionsPaid',
            'advances',
            'journalExpense',
            'total'
        ));
    }

    public function cashflow(Request $request): View
    {
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();

        $cashIds = AccountingChartAccount::query()->where('is_cash_account', true)->pluck('id');
        $jcl = AccountingJournalLine::query()
            ->whereIn('accounting_chart_account_id', $cashIds)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->whereBetween('entry_date', [$from, $to]);
            });
        $journalCashNet = (float) (clone $jcl)->sum('debit') - (float) (clone $jcl)->sum('credit');

        $pettyIn = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_RECEIPT)
            ->whereBetween('entry_date', [$from, $to])
            ->sum('amount');
        $pettyOut = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_DISBURSEMENT)
            ->whereBetween('entry_date', [$from, $to])
            ->sum('amount');
        $pettyNet = $pettyIn - $pettyOut;

        $utilitiesOut = (float) AccountingUtilityPayment::query()
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        $reqPaid = (float) AccountingRequisition::query()
            ->where('status', AccountingRequisition::STATUS_PAID)
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->sum('amount');

        $advancesOut = (float) AccountingSalaryAdvance::query()
            ->where('status', AccountingSalaryAdvance::STATUS_APPROVED)
            ->whereBetween('requested_on', [$from, $to])
            ->sum('amount');

        $operatingNet = $pettyNet - $utilitiesOut - $reqPaid - $advancesOut;
        $combinedEstimate = $journalCashNet + $operatingNet;

        return view('loan.accounting.cashflow', compact(
            'from',
            'to',
            'journalCashNet',
            'pettyIn',
            'pettyOut',
            'pettyNet',
            'utilitiesOut',
            'reqPaid',
            'advancesOut',
            'operatingNet',
            'combinedEstimate'
        ));
    }
}
