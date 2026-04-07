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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Support\TabularExport;

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

        $paginator = $query->paginate(50)->withQueryString();
        $list = $paginator->getCollection();

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
                    '<form method="POST" action="'.route('property.accounting.entries.reverse', $e).'" data-swal-title="Post reversal?" data-swal-confirm="Post reversal for this entry?" data-swal-confirm-text="Yes, reverse">'.
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
                new HtmlString('<input type="checkbox" class="pm-bulk" value="'.(int) $e->id.'" />'),
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
            'columns' => ['Select', 'Date', 'Property', 'Account', 'Category', 'Type', 'Amount', 'Source', 'Reference', 'Description', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $paginator,
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
        $format = strtolower((string) $request->query('format', 'csv'));

        $headers = ['ID', 'Date', 'Property', 'Account', 'Category', 'Type', 'Amount', 'Source', 'Reversal state', 'Reference', 'Description'];
        $rowsClosure = function () use ($rowsData) {
            foreach ($rowsData as $e) {
                yield [
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
                ];
            }
        };

        return TabularExport::stream('property-accounting-entries', $headers, $rowsClosure, $format);
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
        $q = trim($request->string('q')->toString());
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('account_name', 'like', '%'.$q.'%')
                    ->orWhere('reference', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhere('source_key', 'like', '%'.$q.'%');
            });
        }

        $paginator = $query->paginate(50)->withQueryString();
        $rowsData = $paginator->getCollection();

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
                ['label' => 'Rows shown', 'value' => (string) count($rows), 'hint' => 'Current page'],
            ],
            'columns' => ['ID', 'Date', 'Property', 'Account', 'Type', 'Amount', 'Source', 'Reversal state', 'Reference'],
            'tableRows' => $rows,
            'paginator' => $paginator,
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
                'q' => $request->input('q'),
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
        $q = trim($request->string('q')->toString());
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('account_name', 'like', '%'.$q.'%')
                    ->orWhere('reference', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhere('source_key', 'like', '%'.$q.'%');
            });
        }

        $rowsData = $query->limit(5000)->get();
        $format = strtolower((string) $request->query('format', 'csv'));
        $headers = ['ID', 'Date', 'Property', 'Account', 'Category', 'Type', 'Amount', 'Source', 'Reversal state', 'Reference', 'Description'];
        $rowsClosure = function () use ($rowsData) {
            foreach ($rowsData as $e) {
                yield [
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
                ];
            }
        };

        return TabularExport::stream('property-accounting-audit-trail', $headers, $rowsClosure, $format);
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
        $q = trim($request->string('q')->toString());
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('account_name', 'like', '%'.$q.'%')
                    ->orWhere('reference', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhere('source_key', 'like', '%'.$q.'%');
            });
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

    public function bulkEntries(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:reverse_selected'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $ids = array_unique(array_map('intval', $data['ids']));
        if (count($ids) === 0) {
            return back()->withErrors(['ids' => 'No entries selected.']);
        }

        if ($data['action'] === 'reverse_selected') {
            $entries = PmAccountingEntry::query()->whereIn('id', $ids)->get();

            // Determine which are eligible for reversal.
            $alreadyReversedIds = PmAccountingEntry::query()
                ->whereIn('reversal_of_id', $ids)
                ->pluck('reversal_of_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $eligible = $entries->filter(function (PmAccountingEntry $e) use ($alreadyReversedIds) {
                return $e->reversal_of_id === null && ! in_array((int) $e->id, $alreadyReversedIds, true);
            });

            $created = 0;
            DB::transaction(function () use ($request, $eligible, &$created): void {
                foreach ($eligible as $entry) {
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
                    $created++;
                }
            });

            $skipped = count($ids) - $created;
            $msg = "Reversed {$created} entr".($created === 1 ? 'y' : 'ies').($skipped > 0 ? " ({$skipped} skipped)" : '');

            return back()->with('success', $msg);
        }

        return back()->withErrors(['action' => 'Unsupported bulk action.']);
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

    public function trialBalance(Request $request): View
    {
        $from = $request->date('from')?->toDateString();
        $asAt = $request->date('as_at')?->toDateString() ?? now()->toDateString();
        $q = trim($request->string('q')->toString());
        $category = strtolower(trim($request->string('category')->toString()));
        $onlyImbalanced = $request->boolean('only_imbalanced');
        $sort = strtolower(trim($request->string('sort')->toString()));
        $dir = strtolower(trim($request->string('dir')->toString()));
        $perPage = max(10, min(200, (int) $request->query('per_page', 50)));

        $entries = PmAccountingEntry::query()
            ->whereDate('entry_date', '<=', $asAt);
        if ($from) {
            $entries->whereDate('entry_date', '>=', $from);
        }
        if ($q !== '') {
            $entries->where('account_name', 'like', '%'.$q.'%');
        }
        $entries = $entries->get();

        $accounts = $entries->groupBy('account_name')->map(function ($group, $accountName) {
            $debits = (float) $group->where('entry_type', PmAccountingEntry::TYPE_DEBIT)->sum('amount');
            $credits = (float) $group->where('entry_type', PmAccountingEntry::TYPE_CREDIT)->sum('amount');
            $byCategory = $group->groupBy('category')->map(fn ($g) => (float) $g->sum('amount'));
            $dominantCategory = (string) ($byCategory->sortDesc()->keys()->first() ?? '');

            return [
                'account' => $accountName,
                'category' => $dominantCategory,
                'debit' => $debits,
                'credit' => $credits,
                'balance' => $debits - $credits,
            ];
        })->values();

        $validCategories = array_keys(PmAccountingEntry::categoryOptions());
        if ($category !== '' && in_array($category, $validCategories, true)) {
            $accounts = $accounts->where('category', $category)->values();
        }
        if ($onlyImbalanced) {
            $accounts = $accounts->filter(fn (array $a) => abs((float) ($a['balance'] ?? 0)) > 0.0001)->values();
        }

        $sortField = in_array($sort, ['account', 'category', 'debit', 'credit', 'balance'], true) ? $sort : 'account';
        $sortDir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';
        $accounts = $accounts->sortBy($sortField, SORT_NATURAL | SORT_FLAG_CASE, $sortDir === 'desc')->values();

        $totalDebit = (float) $accounts->sum('debit');
        $totalCredit = (float) $accounts->sum('credit');
        $difference = $totalDebit - $totalCredit;
        $isBalanced = abs($difference) < 0.0001;

        $paginator = $this->paginateCollection($request, $accounts->all(), $perPage);
        $pageAccounts = collect($paginator->items());

        $rows = $pageAccounts->map(fn (array $a) => [
            new HtmlString('<a class="text-indigo-600 hover:text-indigo-700 font-medium" href="'.route('property.accounting.entries', ['q' => $a['account']]).'">'.e($a['account']).'</a>'),
            ucfirst((string) ($a['category'] ?: 'other')),
            PropertyMoney::kes($a['debit']),
            PropertyMoney::kes($a['credit']),
            PropertyMoney::kes($a['balance']),
        ])->all();

        return view('property.agent.accounting.reports.trial_balance', [
            'stats' => [
                ['label' => 'Total debit', 'value' => PropertyMoney::kes($totalDebit), 'hint' => 'All accounts'],
                ['label' => 'Total credit', 'value' => PropertyMoney::kes($totalCredit), 'hint' => 'All accounts'],
                ['label' => 'Difference', 'value' => PropertyMoney::kes($difference), 'hint' => $isBalanced ? 'Balanced' : 'Out of balance'],
            ],
            'columns' => ['Account', 'Type', 'Debit', 'Credit', 'Balance (Dr-Cr)'],
            'tableRows' => $rows,
            'paginator' => $paginator,
            'isBalanced' => $isBalanced,
            'difference' => $difference,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'difference' => $difference,
            ],
            'filters' => [
                'q' => $q,
                'from' => $from,
                'as_at' => $asAt,
                'category' => $category,
                'sort' => $sortField,
                'dir' => $sortDir,
                'per_page' => (string) $perPage,
                'only_imbalanced' => $onlyImbalanced ? '1' : '0',
            ],
            'categoryOptions' => PmAccountingEntry::categoryOptions(),
        ]);
    }

    public function incomeStatement(Request $request): View
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->endOfMonth()->toDateString();
        $propertyId = (int) $request->integer('property_id');
        $perPage = max(10, min(200, (int) $request->query('per_page', 30)));

        $queryBase = PmAccountingEntry::query()
            ->with('property')
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to);
        if ($propertyId > 0) {
            $queryBase->where('property_id', $propertyId);
        }

        $income = (float) (clone $queryBase)
            ->where('category', PmAccountingEntry::CATEGORY_INCOME)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->sum('amount');
        $expenses = (float) (clone $queryBase)
            ->where('category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->sum('amount');
        $net = $income - $expenses;

        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate = \Carbon\Carbon::parse($to)->endOfDay();
        $days = max(1, $fromDate->diffInDays($toDate) + 1);
        $prevFrom = $fromDate->copy()->subDays($days)->toDateString();
        $prevTo = $fromDate->copy()->subDay()->toDateString();

        $prevBase = PmAccountingEntry::query()
            ->whereDate('entry_date', '>=', $prevFrom)
            ->whereDate('entry_date', '<=', $prevTo);
        if ($propertyId > 0) {
            $prevBase->where('property_id', $propertyId);
        }
        $prevIncome = (float) (clone $prevBase)
            ->where('category', PmAccountingEntry::CATEGORY_INCOME)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->sum('amount');
        $prevExpenses = (float) (clone $prevBase)
            ->where('category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->sum('amount');
        $prevNet = $prevIncome - $prevExpenses;

        $incomeBreakdown = (clone $queryBase)
            ->where('category', PmAccountingEntry::CATEGORY_INCOME)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->selectRaw('account_name, COALESCE(SUM(amount),0) as total')
            ->groupBy('account_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'account' => (string) $r->account_name,
                'total' => (float) $r->total,
                'pct' => $income > 0 ? round(((float) $r->total / $income) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();

        $expenseBreakdown = (clone $queryBase)
            ->where('category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->selectRaw('account_name, COALESCE(SUM(amount),0) as total')
            ->groupBy('account_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'account' => (string) $r->account_name,
                'total' => (float) $r->total,
                'pct' => $expenses > 0 ? round(((float) $r->total / $expenses) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();

        $propertyBreakdown = (clone $queryBase)
            ->leftJoin('properties', 'properties.id', '=', 'pm_accounting_entries.property_id')
            ->selectRaw("COALESCE(properties.name, 'General') as property_name")
            ->selectRaw("COALESCE(SUM(CASE WHEN pm_accounting_entries.category = ? AND pm_accounting_entries.entry_type = ? THEN pm_accounting_entries.amount ELSE 0 END),0) as income_total", [PmAccountingEntry::CATEGORY_INCOME, PmAccountingEntry::TYPE_CREDIT])
            ->selectRaw("COALESCE(SUM(CASE WHEN pm_accounting_entries.category = ? AND pm_accounting_entries.entry_type = ? THEN pm_accounting_entries.amount ELSE 0 END),0) as expense_total", [PmAccountingEntry::CATEGORY_EXPENSE, PmAccountingEntry::TYPE_DEBIT])
            ->groupBy('properties.name')
            ->orderBy('property_name')
            ->get()
            ->map(fn ($r) => [
                'property' => (string) $r->property_name,
                'income' => (float) $r->income_total,
                'expenses' => (float) $r->expense_total,
                'net' => (float) $r->income_total - (float) $r->expense_total,
            ])
            ->values()
            ->all();

        $trendStart = now()->startOfMonth()->subMonths(5);
        $trendEnd = now()->endOfMonth();
        $trendBase = PmAccountingEntry::query()
            ->whereDate('entry_date', '>=', $trendStart->toDateString())
            ->whereDate('entry_date', '<=', $trendEnd->toDateString());
        if ($propertyId > 0) {
            $trendBase->where('property_id', $propertyId);
        }
        $trendRows = (clone $trendBase)
            ->selectRaw("DATE_FORMAT(entry_date, '%Y-%m') as ym")
            ->selectRaw("COALESCE(SUM(CASE WHEN category = ? AND entry_type = ? THEN amount ELSE 0 END),0) as income_total", [PmAccountingEntry::CATEGORY_INCOME, PmAccountingEntry::TYPE_CREDIT])
            ->selectRaw("COALESCE(SUM(CASE WHEN category = ? AND entry_type = ? THEN amount ELSE 0 END),0) as expense_total", [PmAccountingEntry::CATEGORY_EXPENSE, PmAccountingEntry::TYPE_DEBIT])
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');
        $trend = collect(range(0, 5))->map(function ($i) use ($trendStart, $trendRows) {
            $m = $trendStart->copy()->addMonths($i);
            $ym = $m->format('Y-m');
            $row = $trendRows->get($ym);
            $incomeVal = (float) ($row->income_total ?? 0);
            $expenseVal = (float) ($row->expense_total ?? 0);
            return [
                'label' => $m->format('M Y'),
                'income' => $incomeVal,
                'expenses' => $expenseVal,
                'net' => $incomeVal - $expenseVal,
            ];
        })->all();

        $txnPaginator = (clone $queryBase)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'txn_page')
            ->withQueryString();

        return view('property.agent.accounting.reports.income_statement', [
            'income' => PropertyMoney::kes($income),
            'expenses' => PropertyMoney::kes($expenses),
            'net' => PropertyMoney::kes($net),
            'noi' => PropertyMoney::kes($net),
            'incomeRaw' => $income,
            'expensesRaw' => $expenses,
            'netRaw' => $net,
            'prevIncomeRaw' => $prevIncome,
            'prevExpensesRaw' => $prevExpenses,
            'prevNetRaw' => $prevNet,
            'incomeBreakdown' => $incomeBreakdown,
            'expenseBreakdown' => $expenseBreakdown,
            'propertyBreakdown' => $propertyBreakdown,
            'trend' => $trend,
            'txnPaginator' => $txnPaginator,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'periodLabel' => \Carbon\Carbon::parse($from)->format('d M Y').' - '.\Carbon\Carbon::parse($to)->format('d M Y'),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'property_id' => $propertyId > 0 ? (string) $propertyId : '',
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function cashBook(Request $request): View
    {
        $rowsRaw = PmAccountingEntry::query()
            ->where('account_name', 'like', '%cash%')
            ->orWhere('account_name', 'like', '%bank%')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('to')))
            ->when(trim($request->string('q')->toString()) !== '', function ($q) use ($request) {
                $s = trim($request->string('q')->toString());
                $q->where(function ($sub) use ($s) {
                    $sub->where('account_name', 'like', '%'.$s.'%')
                        ->orWhere('description', 'like', '%'.$s.'%')
                        ->orWhere('reference', 'like', '%'.$s.'%');
                });
            })
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

        $paginator = $this->paginateCollection($request, $rows, 50);

        return view('property.agent.accounting.reports.cash_book', [
            'columns' => ['Date', 'Account', 'Description', 'Debit', 'Credit', 'Running balance'],
            'tableRows' => $paginator->items(),
            'stats' => [
                ['label' => 'Rows', 'value' => (string) count($rows), 'hint' => 'Cash/Bank records'],
            ],
            'paginator' => $paginator,
            'filters' => ['from' => $request->input('from'), 'to' => $request->input('to'), 'q' => $request->input('q')],
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

        $paginator = (clone $base)->orderByDesc('entry_date')->orderByDesc('id')->paginate(50)->withQueryString();
        $rows = $paginator->getCollection()
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
            'paginator' => $paginator,
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

    public function payrollPayslips(Request $request): View
    {
        $itemsQuery = PmAccountingEntry::query()
            ->where('source_key', 'like', 'payroll%')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('to')))
            ->when(trim($request->string('q')->toString()) !== '', function ($q) use ($request) {
                $s = trim($request->string('q')->toString());
                $q->where(function ($sub) use ($s) {
                    $sub->where('reference', 'like', '%'.$s.'%')
                        ->orWhere('account_name', 'like', '%'.$s.'%')
                        ->orWhere('description', 'like', '%'.$s.'%');
                });
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        $paginator = $itemsQuery->paginate(50)->withQueryString();
        $items = $paginator->getCollection();

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
            'paginator' => $paginator,
            'filters' => ['from' => $request->input('from'), 'to' => $request->input('to'), 'q' => $request->input('q')],
        ]);
    }

    public function exportTrialBalanceCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from')?->toDateString();
        $asAt = $request->date('as_at')?->toDateString() ?? now()->toDateString();
        $q = trim($request->string('q')->toString());
        $category = strtolower(trim($request->string('category')->toString()));

        $entries = PmAccountingEntry::query()->whereDate('entry_date', '<=', $asAt);
        if ($from) {
            $entries->whereDate('entry_date', '>=', $from);
        }
        if ($q !== '') {
            $entries->where('account_name', 'like', '%'.$q.'%');
        }
        $grouped = $entries->get()->groupBy('account_name');
        $format = strtolower((string) $request->query('format', 'csv'));
        $headers = ['Account', 'Type', 'Debit', 'Credit', 'Balance'];
        $rowsClosure = function () use ($grouped, $category) {
            foreach ($grouped as $accountName => $group) {
                $byCategory = $group->groupBy('category')->map(fn ($g) => (float) $g->sum('amount'));
                $dominantCategory = (string) ($byCategory->sortDesc()->keys()->first() ?? '');
                if ($category !== '' && $dominantCategory !== $category) {
                    continue;
                }
                $debit = (float) $group->where('entry_type', PmAccountingEntry::TYPE_DEBIT)->sum('amount');
                $credit = (float) $group->where('entry_type', PmAccountingEntry::TYPE_CREDIT)->sum('amount');
                yield [
                    (string) $accountName,
                    $dominantCategory,
                    (string) $debit,
                    (string) $credit,
                    (string) ($debit - $credit),
                ];
            }
        };

        return TabularExport::stream('property-accounting-trial-balance', $headers, $rowsClosure, $format);
    }

    public function exportIncomeStatementCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->endOfMonth()->toDateString();
        $propertyId = (int) $request->integer('property_id');
        $queryBase = PmAccountingEntry::query()
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to);
        if ($propertyId > 0) {
            $queryBase->where('property_id', $propertyId);
        }
        $income = (float) (clone $queryBase)->where('category', PmAccountingEntry::CATEGORY_INCOME)->where('entry_type', PmAccountingEntry::TYPE_CREDIT)->sum('amount');
        $expenses = (float) (clone $queryBase)->where('category', PmAccountingEntry::CATEGORY_EXPENSE)->where('entry_type', PmAccountingEntry::TYPE_DEBIT)->sum('amount');
        $incomeLines = (clone $queryBase)
            ->where('category', PmAccountingEntry::CATEGORY_INCOME)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->selectRaw('account_name, COALESCE(SUM(amount),0) as total')
            ->groupBy('account_name')
            ->orderByDesc('total')
            ->get();
        $expenseLines = (clone $queryBase)
            ->where('category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->selectRaw('account_name, COALESCE(SUM(amount),0) as total')
            ->groupBy('account_name')
            ->orderByDesc('total')
            ->get();
        $format = strtolower((string) $request->query('format', 'csv'));
        $headers = ['Section', 'Line', 'Amount'];
        $rowsClosure = function () use ($from, $to, $income, $expenses, $incomeLines, $expenseLines) {
            yield ['Period', $from.' to '.$to, ''];
            yield ['Summary', 'Income', (string) $income];
            foreach ($incomeLines as $line) {
                yield ['Income breakdown', (string) $line->account_name, (string) $line->total];
            }
            yield ['Summary', 'Expenses', (string) $expenses];
            foreach ($expenseLines as $line) {
                yield ['Expense breakdown', (string) $line->account_name, (string) $line->total];
            }
            yield ['Summary', 'Net / NOI', (string) ($income - $expenses)];
        };

        return TabularExport::stream('property-accounting-income-statement', $headers, $rowsClosure, $format);
    }

    public function exportCashBookCsv(Request $request): StreamedResponse
    {
        $rowsRaw = PmAccountingEntry::query()
            ->where('account_name', 'like', '%cash%')
            ->orWhere('account_name', 'like', '%bank%')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('to')))
            ->when(trim($request->string('q')->toString()) !== '', function ($q) use ($request) {
                $s = trim($request->string('q')->toString());
                $q->where(function ($sub) use ($s) {
                    $sub->where('account_name', 'like', '%'.$s.'%')
                        ->orWhere('description', 'like', '%'.$s.'%')
                        ->orWhere('reference', 'like', '%'.$s.'%');
                });
            })
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
        $format = strtolower((string) $request->query('format', 'csv'));
        $headers = ['Date', 'Account', 'Description', 'Debit', 'Credit', 'Running balance'];
        $rowsClosure = function () use ($rowsRaw) {
            $running = 0.0;
            foreach ($rowsRaw as $e) {
                $debit = $e->entry_type === PmAccountingEntry::TYPE_DEBIT ? (float) $e->amount : 0.0;
                $credit = $e->entry_type === PmAccountingEntry::TYPE_CREDIT ? (float) $e->amount : 0.0;
                $running += $debit - $credit;
                yield [
                    $e->entry_date?->format('Y-m-d') ?? '',
                    $e->account_name,
                    $e->description ?? '',
                    (string) $debit,
                    (string) $credit,
                    (string) $running,
                ];
            }
        };

        return TabularExport::stream('property-accounting-cash-book', $headers, $rowsClosure, $format);
    }

    public function exportPayrollPayslipsCsv(Request $request): StreamedResponse
    {
        $items = PmAccountingEntry::query()
            ->where('source_key', 'like', 'payroll%')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('to')))
            ->when(trim($request->string('q')->toString()) !== '', function ($q) use ($request) {
                $s = trim($request->string('q')->toString());
                $q->where(function ($sub) use ($s) {
                    $sub->where('reference', 'like', '%'.$s.'%')
                        ->orWhere('account_name', 'like', '%'.$s.'%')
                        ->orWhere('description', 'like', '%'.$s.'%');
                });
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(5000)
            ->get();
        $format = strtolower((string) $request->query('format', 'csv'));
        $headers = ['Entry', 'Date', 'Reference', 'Account', 'Type', 'Amount'];
        $rowsClosure = function () use ($items) {
            foreach ($items as $e) {
                yield [
                    (string) $e->id,
                    $e->entry_date?->format('Y-m-d') ?? '',
                    $e->reference ?? '',
                    $e->account_name,
                    $e->entry_type,
                    (string) $e->amount,
                ];
            }
        };

        return TabularExport::stream('property-accounting-payroll-ledger', $headers, $rowsClosure, $format);
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
        $companyName = PropertyPortalSetting::getValue('company_name', 'Property Management');
        $logoRaw = trim((string) PropertyPortalSetting::getValue('company_logo_url', ''));
        $logoUrl = null;
        if ($logoRaw !== '') {
            $logoUrl = str_starts_with($logoRaw, 'http://')
                || str_starts_with($logoRaw, 'https://')
                || str_starts_with($logoRaw, '/')
                ? $logoRaw
                : asset($logoRaw);
        }

        return view('property.agent.accounting.payroll.payslip', [
            'reference' => $reference,
            'entryDate' => $first?->entry_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'employeeName' => (string) ($meta['employee_name'] ?? 'Employee'),
            'companyName' => $companyName,
            'companyLogoUrl' => $logoUrl,
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

    /**
     * @template T
     *
     * @param  list<T>  $items
     */
    private function paginateCollection(Request $request, array $items, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}

