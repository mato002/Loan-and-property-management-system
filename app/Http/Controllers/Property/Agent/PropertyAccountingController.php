<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmAccountingEntry;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyAccountingController extends Controller
{
    public function index(): View
    {
        $entries = PmAccountingEntry::query()->count();
        $monthBase = PmAccountingEntry::query()
            ->whereYear('entry_date', now()->year)
            ->whereMonth('entry_date', now()->month);

        $income = (float) (clone $monthBase)
            ->where('category', PmAccountingEntry::CATEGORY_INCOME)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->sum('amount');

        $expenses = (float) (clone $monthBase)
            ->where('category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->sum('amount');

        return view('property.agent.accounting.index', [
            'stats' => [
                ['label' => 'Entries', 'value' => (string) $entries, 'hint' => 'Journal rows'],
                ['label' => 'Income (MTD)', 'value' => PropertyMoney::kes($income), 'hint' => 'Credits'],
                ['label' => 'Expenses (MTD)', 'value' => PropertyMoney::kes($expenses), 'hint' => 'Debits'],
                ['label' => 'Net (MTD)', 'value' => PropertyMoney::kes($income - $expenses), 'hint' => 'Income - expense'],
            ],
        ]);
    }

    public function entries(Request $request): View
    {
        $query = $this->buildEntriesQuery($request);
        $reversalFilter = $request->string('reversal')->toString();
        $sourceFilter = $request->string('source_key')->toString();

        $list = $query->limit(300)->get();

        $reversedIds = PmAccountingEntry::query()
            ->whereNotNull('reversal_of_id')
            ->pluck('reversal_of_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        $rows = $list->map(function (PmAccountingEntry $e) use ($reversedIds) {
            $isReversed = in_array((int) $e->id, $reversedIds, true);
            $isReverseRow = $e->reversal_of_id !== null;

            $action = '—';
            if (! $isReverseRow && ! $isReversed) {
                $action = new HtmlString(
                    '<form method="POST" action="'.route('property.accounting.entries.reverse', $e).'" onsubmit="return confirm(\'Post reversal for this entry?\');">'.
                    csrf_field().
                    '<button type="submit" class="text-rose-600 hover:text-rose-700 font-medium">Reverse</button>'.
                    '</form>'
                );
            } elseif ($isReversed) {
                $action = 'Reversed';
            } elseif ($isReverseRow) {
                $action = 'Reversal row';
            }

            return [
                $e->entry_date?->format('Y-m-d') ?? '—',
                $e->property?->name ?? 'General',
                $e->account_name,
                ucfirst($e->category),
                ucfirst($e->entry_type),
                PropertyMoney::kes((float) $e->amount),
                $e->source_key ?: 'manual_entry',
                $e->reference ?: '—',
                $e->description ?: '—',
                $action,
            ];
        })->all();

        return view('property.agent.accounting.entries', [
            'stats' => [
                ['label' => 'Rows shown', 'value' => (string) count($rows), 'hint' => 'Latest first'],
            ],
            'columns' => ['Date', 'Property', 'Account', 'Category', 'Type', 'Amount', 'Source', 'Reference', 'Description', 'Actions'],
            'tableRows' => $rows,
            'properties' => Property::query()->orderBy('name')->get(),
            'categoryOptions' => PmAccountingEntry::categoryOptions(),
            'typeOptions' => PmAccountingEntry::typeOptions(),
            'accountMap' => PropertyAccountingPostingService::accountMap(),
            'sourceOptions' => PmAccountingEntry::query()
                ->whereNotNull('source_key')
                ->distinct()
                ->orderBy('source_key')
                ->pluck('source_key')
                ->values(),
            'reversalFilter' => $reversalFilter,
            'sourceFilter' => $sourceFilter,
        ]);
    }

    public function exportEntriesCsv(Request $request): StreamedResponse
    {
        $rowsData = $this->buildEntriesQuery($request)->limit(5000)->get();

        $rows = $rowsData->map(fn (PmAccountingEntry $e) => [
            (string) $e->id,
            $e->entry_date?->format('Y-m-d') ?? '',
            $e->property?->name ?? 'General',
            $e->account_name,
            $e->category,
            $e->entry_type,
            (string) $e->amount,
            $e->source_key ?? 'manual_entry',
            $e->reversal_of_id ? ('reversal_of_'.$e->reversal_of_id) : 'original',
            $e->reference ?? '',
            $e->description ?? '',
        ])->all();

        return $this->streamCsv(
            'property-accounting-entries.csv',
            ['ID', 'Date', 'Property', 'Account', 'Category', 'Type', 'Amount', 'Source', 'Reversal state', 'Reference', 'Description'],
            $rows
        );
    }

    public function auditTrail(Request $request): View
    {
        $query = PmAccountingEntry::query()
            ->with(['property'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->date('to'));
        }
        if ($request->filled('source_key')) {
            $query->where('source_key', $request->string('source_key')->toString());
        }

        $rowsData = $query->limit(500)->get();

        $rows = $rowsData->map(fn (PmAccountingEntry $e) => [
            '#'.$e->id,
            $e->entry_date?->format('Y-m-d') ?? '—',
            $e->property?->name ?? 'General',
            $e->account_name,
            ucfirst($e->entry_type),
            PropertyMoney::kes((float) $e->amount),
            $e->source_key ?: 'manual_entry',
            $e->reversal_of_id ? ('Reversal of #'.$e->reversal_of_id) : 'Original',
            $e->reference ?: '—',
        ])->all();

        return view('property.agent.accounting.audit_trail', [
            'stats' => [
                ['label' => 'Rows shown', 'value' => (string) count($rows), 'hint' => 'Latest 500 max'],
            ],
            'columns' => ['ID', 'Date', 'Property', 'Account', 'Type', 'Amount', 'Source', 'Reversal state', 'Reference'],
            'tableRows' => $rows,
            'sourceOptions' => PmAccountingEntry::query()
                ->whereNotNull('source_key')
                ->distinct()
                ->orderBy('source_key')
                ->pluck('source_key')
                ->values(),
            'filters' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'source_key' => $request->input('source_key'),
            ],
        ]);
    }

    public function exportAuditTrailCsv(Request $request): StreamedResponse
    {
        $query = PmAccountingEntry::query()
            ->with('property')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->date('to'));
        }
        if ($request->filled('source_key')) {
            $query->where('source_key', $request->string('source_key')->toString());
        }

        $rows = $query->limit(5000)->get()->map(fn (PmAccountingEntry $e) => [
            (string) $e->id,
            $e->entry_date?->format('Y-m-d') ?? '',
            $e->property?->name ?? 'General',
            $e->account_name,
            $e->category,
            $e->entry_type,
            (string) $e->amount,
            $e->source_key ?? 'manual_entry',
            $e->reversal_of_id ? ('reversal_of_'.$e->reversal_of_id) : 'original',
            $e->reference ?? '',
            $e->description ?? '',
        ])->all();

        return $this->streamCsv(
            'property-accounting-audit-trail.csv',
            ['ID', 'Date', 'Property', 'Account', 'Category', 'Type', 'Amount', 'Source', 'Reversal state', 'Reference', 'Description'],
            $rows
        );
    }

    /**
     * @param list<string> $header
     * @param list<list<string>> $rows
     */
    protected function streamCsv(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildEntriesQuery(Request $request)
    {
        $query = PmAccountingEntry::query()
            ->with('property')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        $reversalFilter = $request->string('reversal')->toString();
        if ($reversalFilter === 'only_reversals') {
            $query->whereNotNull('reversal_of_id');
        } elseif ($reversalFilter === 'without_reversals') {
            $query->whereNull('reversal_of_id');
        }

        $sourceFilter = $request->string('source_key')->toString();
        if ($sourceFilter !== '') {
            $query->where('source_key', $sourceFilter);
        }

        return $query;
    }

    public function storeEntry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'property_id' => ['nullable', 'exists:properties,id'],
            'account_name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:'.implode(',', array_keys(PmAccountingEntry::categoryOptions()))],
            'entry_type' => ['required', 'in:'.implode(',', array_keys(PmAccountingEntry::typeOptions()))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        PmAccountingEntry::query()->create([
            ...$data,
            'recorded_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Accounting entry recorded.');
    }

    public function reverseEntry(Request $request, PmAccountingEntry $entry): RedirectResponse
    {
        if ($entry->reversal_of_id !== null) {
            return back()->withErrors(['entry' => 'Reversal rows cannot be reversed again.']);
        }

        $exists = PmAccountingEntry::query()->where('reversal_of_id', $entry->id)->exists();
        if ($exists) {
            return back()->withErrors(['entry' => 'This entry is already reversed.']);
        }

        $reverseType = $entry->entry_type === PmAccountingEntry::TYPE_DEBIT
            ? PmAccountingEntry::TYPE_CREDIT
            : PmAccountingEntry::TYPE_DEBIT;

        PmAccountingEntry::query()->create([
            'property_id' => $entry->property_id,
            'recorded_by_user_id' => $request->user()->id,
            'entry_date' => now()->toDateString(),
            'account_name' => $entry->account_name,
            'category' => $entry->category,
            'entry_type' => $reverseType,
            'amount' => (float) $entry->amount,
            'reference' => 'REV-'.($entry->reference ?: $entry->id),
            'description' => 'Reversal of entry #'.$entry->id,
            'reversal_of_id' => $entry->id,
            'source_key' => 'manual_reversal',
        ]);

        return back()->with('success', 'Reversal entry posted.');
    }

    public function saveAccountMap(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accounts_receivable' => ['required', 'string', 'max:120'],
            'rental_income' => ['required', 'string', 'max:120'],
            'cash_bank' => ['required', 'string', 'max:120'],
            'maintenance_expense' => ['required', 'string', 'max:120'],
            'accounts_payable' => ['required', 'string', 'max:120'],
        ]);

        PropertyPortalSetting::query()->updateOrCreate(
            ['key' => 'property_accounting_account_map'],
            ['value' => json_encode($data, JSON_UNESCAPED_UNICODE)]
        );

        return back()->with('success', 'Account mapping saved.');
    }

    public function trialBalance(): View
    {
        $entries = PmAccountingEntry::query()->get();
        $accounts = $entries->groupBy('account_name')->map(function ($group, $accountName) {
            $debits = (float) $group->where('entry_type', PmAccountingEntry::TYPE_DEBIT)->sum('amount');
            $credits = (float) $group->where('entry_type', PmAccountingEntry::TYPE_CREDIT)->sum('amount');

            return [
                'account' => $accountName,
                'debit' => $debits,
                'credit' => $credits,
            ];
        })->sortBy('account')->values();

        $rows = $accounts->map(fn (array $a) => [
            $a['account'],
            PropertyMoney::kes($a['debit']),
            PropertyMoney::kes($a['credit']),
        ])->all();

        return view('property.agent.accounting.reports.trial_balance', [
            'stats' => [
                ['label' => 'Total debit', 'value' => PropertyMoney::kes((float) $accounts->sum('debit')), 'hint' => 'All accounts'],
                ['label' => 'Total credit', 'value' => PropertyMoney::kes((float) $accounts->sum('credit')), 'hint' => 'All accounts'],
            ],
            'columns' => ['Account', 'Debit', 'Credit'],
            'tableRows' => $rows,
        ]);
    }

    public function incomeStatement(): View
    {
        $income = (float) PmAccountingEntry::query()
            ->where('category', PmAccountingEntry::CATEGORY_INCOME)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->sum('amount');
        $expenses = (float) PmAccountingEntry::query()
            ->where('category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->sum('amount');

        return view('property.agent.accounting.reports.income_statement', [
            'income' => PropertyMoney::kes($income),
            'expenses' => PropertyMoney::kes($expenses),
            'net' => PropertyMoney::kes($income - $expenses),
        ]);
    }

    public function cashBook(): View
    {
        $rowsRaw = PmAccountingEntry::query()
            ->where('account_name', 'like', '%cash%')
            ->orWhere('account_name', 'like', '%bank%')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        $rows = $rowsRaw->map(function (PmAccountingEntry $e) use (&$running) {
            $debit = $e->entry_type === PmAccountingEntry::TYPE_DEBIT ? (float) $e->amount : 0.0;
            $credit = $e->entry_type === PmAccountingEntry::TYPE_CREDIT ? (float) $e->amount : 0.0;
            $running += $debit - $credit;

            return [
                $e->entry_date?->format('Y-m-d') ?? '—',
                $e->account_name,
                $e->description ?: '—',
                PropertyMoney::kes($debit),
                PropertyMoney::kes($credit),
                PropertyMoney::kes($running),
            ];
        })->all();

        return view('property.agent.accounting.reports.cash_book', [
            'columns' => ['Date', 'Account', 'Description', 'Debit', 'Credit', 'Running balance'],
            'tableRows' => $rows,
            'stats' => [
                ['label' => 'Rows', 'value' => (string) count($rows), 'hint' => 'Cash/Bank records'],
            ],
        ]);
    }

    public function payroll(): View
    {
        $base = PmAccountingEntry::query()->where('source_key', 'like', 'payroll%');
        $mtdBase = (clone $base)
            ->whereYear('entry_date', now()->year)
            ->whereMonth('entry_date', now()->month);

        $grossMtd = (float) (clone $mtdBase)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->where('category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->sum('amount');
        $liabilityMtd = (float) (clone $mtdBase)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->sum('amount');

        $rows = (clone $base)->orderByDesc('entry_date')->orderByDesc('id')->limit(30)->get()
            ->map(fn (PmAccountingEntry $e) => [
                $e->entry_date?->format('Y-m-d') ?? '—',
                $e->account_name,
                ucfirst($e->entry_type),
                PropertyMoney::kes((float) $e->amount),
                $e->reference ?: '—',
                $e->description ?: '—',
            ])->all();

        return view('property.agent.accounting.payroll.index', [
            'stats' => [
                ['label' => 'Payroll expense (MTD)', 'value' => PropertyMoney::kes($grossMtd), 'hint' => 'Debit expense'],
                ['label' => 'Payroll liability (MTD)', 'value' => PropertyMoney::kes($liabilityMtd), 'hint' => 'Credit liability'],
                ['label' => 'Payroll rows', 'value' => (string) count($rows), 'hint' => 'Latest 30'],
            ],
            'columns' => ['Date', 'Account', 'Type', 'Amount', 'Reference', 'Description'],
            'tableRows' => $rows,
        ]);
    }

    public function payrollStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'gross_amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $raw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $expenseAccount = (string) ($settings['expense_account'] ?? 'Payroll Expense');
        $payableAccount = (string) ($settings['payable_account'] ?? 'Payroll Payable');

        DB::transaction(function () use ($request, $data, $expenseAccount, $payableAccount): void {
            $common = [
                'property_id' => null,
                'recorded_by_user_id' => $request->user()->id,
                'entry_date' => $data['entry_date'],
                'amount' => (float) $data['gross_amount'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'Monthly payroll posting',
                'source_key' => 'payroll_batch',
            ];

            // Debit payroll expense.
            PmAccountingEntry::query()->create([
                ...$common,
                'account_name' => $expenseAccount,
                'category' => PmAccountingEntry::CATEGORY_EXPENSE,
                'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            ]);

            // Credit payroll payable.
            PmAccountingEntry::query()->create([
                ...$common,
                'account_name' => $payableAccount,
                'category' => PmAccountingEntry::CATEGORY_LIABILITY,
                'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            ]);
        });

        return redirect()->route('property.accounting.payroll')->with('success', 'Payroll batch posted.');
    }

    public function payrollPayslips(): View
    {
        $items = PmAccountingEntry::query()
            ->where('source_key', 'like', 'payroll%')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $rows = $items->map(function (PmAccountingEntry $e) {
            $reference = $e->reference ?: '—';
            $open = '—';
            if ($e->source_key === 'payroll_employee' && $e->reference) {
                $url = route('property.accounting.payroll.payslips.show', ['reference' => $e->reference]);
                $open = new HtmlString('<a href="'.$url.'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open payslip</a>');
            }

            return [
                '#'.$e->id,
                $e->entry_date?->format('Y-m-d') ?? '—',
                $reference,
                $e->account_name,
                ucfirst($e->entry_type),
                PropertyMoney::kes((float) $e->amount),
                $open,
            ];
        })->all();

        return view('property.agent.accounting.payroll.payslips', [
            'stats' => [
                ['label' => 'Rows shown', 'value' => (string) count($rows), 'hint' => 'Latest 200'],
            ],
            'columns' => ['Entry', 'Date', 'Reference', 'Account', 'Type', 'Amount', 'Payslip'],
            'tableRows' => $rows,
        ]);
    }

    public function payrollSettings(): View
    {
        $raw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

        return view('property.agent.accounting.payroll.settings', [
            'settings' => $settings,
        ]);
    }

    public function payrollSettingsSave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_account' => ['required', 'string', 'max:120'],
            'payable_account' => ['required', 'string', 'max:120'],
            'deductions_payable_account' => ['required', 'string', 'max:120'],
            'default_posting_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'lock_processed_periods' => ['nullable', 'boolean'],
        ]);
        $data['lock_processed_periods'] = $request->boolean('lock_processed_periods');

        PropertyPortalSetting::query()->updateOrCreate(
            ['key' => 'property_payroll_settings'],
            ['value' => json_encode($data, JSON_UNESCAPED_UNICODE)]
        );

        return back()->with('success', 'Payroll settings saved.');
    }

    public function payrollEmployeeStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'employee_name' => ['required', 'string', 'max:120'],
            'basic_pay' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $allowances = (float) ($data['allowances'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        $basic = (float) $data['basic_pay'];
        $gross = $basic + $allowances;
        $net = $gross - $deductions;

        if ($net <= 0) {
            return back()->withErrors(['deductions' => 'Deductions cannot be equal to or exceed gross pay.'])->withInput();
        }

        $raw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $expenseAccount = (string) ($settings['expense_account'] ?? 'Payroll Expense');
        $payableAccount = (string) ($settings['payable_account'] ?? 'Payroll Payable');
        $deductionsPayableAccount = (string) ($settings['deductions_payable_account'] ?? 'Payroll Deductions Payable');

        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'PSL-'.date('Ym').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        }

        $description = $this->buildPayrollEmployeeMeta([
            'employee_name' => (string) $data['employee_name'],
            'basic_pay' => $basic,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'gross_pay' => $gross,
            'net_pay' => $net,
        ]);

        DB::transaction(function () use ($request, $data, $reference, $description, $gross, $net, $deductions, $expenseAccount, $payableAccount, $deductionsPayableAccount): void {
            $common = [
                'property_id' => null,
                'recorded_by_user_id' => $request->user()->id,
                'entry_date' => $data['entry_date'],
                'reference' => $reference,
                'description' => $description,
                'source_key' => 'payroll_employee',
            ];

            PmAccountingEntry::query()->create([
                ...$common,
                'account_name' => $expenseAccount,
                'category' => PmAccountingEntry::CATEGORY_EXPENSE,
                'entry_type' => PmAccountingEntry::TYPE_DEBIT,
                'amount' => $gross,
            ]);

            PmAccountingEntry::query()->create([
                ...$common,
                'account_name' => $payableAccount,
                'category' => PmAccountingEntry::CATEGORY_LIABILITY,
                'entry_type' => PmAccountingEntry::TYPE_CREDIT,
                'amount' => $net,
            ]);

            if ($deductions > 0) {
                PmAccountingEntry::query()->create([
                    ...$common,
                    'account_name' => $deductionsPayableAccount,
                    'category' => PmAccountingEntry::CATEGORY_LIABILITY,
                    'entry_type' => PmAccountingEntry::TYPE_CREDIT,
                    'amount' => $deductions,
                ]);
            }
        });

        return redirect()->route('property.accounting.payroll.payslips.show', ['reference' => $reference])->with('success', 'Employee payroll posted.');
    }

    public function payrollPayslipShow(string $reference): View
    {
        $entries = PmAccountingEntry::query()
            ->where('source_key', 'payroll_employee')
            ->where('reference', $reference)
            ->orderBy('id')
            ->get();

        abort_if($entries->isEmpty(), 404);

        $first = $entries->first();
        $meta = $this->parsePayrollEmployeeMeta((string) ($first?->description ?? ''));

        $gross = (float) $entries
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->sum('amount');
        $credits = (float) $entries
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->sum('amount');
        $deductions = max(0.0, $credits - (float) ($meta['net_pay'] ?? 0));
        $net = (float) ($meta['net_pay'] ?? ($gross - $deductions));

        return view('property.agent.accounting.payroll.payslip', [
            'reference' => $reference,
            'entryDate' => $first?->entry_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'employeeName' => (string) ($meta['employee_name'] ?? 'Employee'),
            'basicPay' => (float) ($meta['basic_pay'] ?? 0),
            'allowances' => (float) ($meta['allowances'] ?? 0),
            'grossPay' => $gross,
            'deductions' => $deductions,
            'netPay' => $net,
            'entries' => $entries,
        ]);
    }

    /**
     * @param array{employee_name:string,basic_pay:float,allowances:float,deductions:float,gross_pay:float,net_pay:float} $data
     */
    private function buildPayrollEmployeeMeta(array $data): string
    {
        return implode('|', [
            'PAYROLL_EMPLOYEE',
            'employee_name='.str_replace('|', ' ', $data['employee_name']),
            'basic_pay='.$data['basic_pay'],
            'allowances='.$data['allowances'],
            'deductions='.$data['deductions'],
            'gross_pay='.$data['gross_pay'],
            'net_pay='.$data['net_pay'],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function parsePayrollEmployeeMeta(string $description): array
    {
        if (! str_starts_with($description, 'PAYROLL_EMPLOYEE|')) {
            return [];
        }

        $out = [];
        $parts = explode('|', $description);
        foreach ($parts as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $out[$key] = is_numeric($value) ? (float) $value : $value;
        }

        return $out;
    }
}

