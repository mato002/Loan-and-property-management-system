<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\SendPayrollPayslipEmailJob;
use App\Models\AccountingChartAccount;
use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPayrollLine;
use App\Models\AccountingPayrollPeriod;
use App\Models\AccountingPeriod;
use App\Models\Employee;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLandlordPayout;
use App\Models\PmMaintenanceJob;
use App\Models\PmMessageDelivery;
use App\Models\PmAccountingEntry;
use App\Models\PmTenant;
use App\Models\PmPayment;
use App\Models\Property;
use App\Models\UnassignedPayment;
use App\Models\PropertyPortalSetting;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Support\TabularExport;

class PropertyAccountingController extends Controller
{
    public function index(): View
    {
        $monthBase = PmAccountingEntry::query()
            ->whereYear('entry_date', now()->year)
            ->whereMonth('entry_date', now()->month);
        $income = (float) (clone $monthBase)->where('category', PmAccountingEntry::CATEGORY_INCOME)->where('entry_type', PmAccountingEntry::TYPE_CREDIT)->sum('amount');
        $expenses = (float) (clone $monthBase)->where('category', PmAccountingEntry::CATEGORY_EXPENSE)->where('entry_type', PmAccountingEntry::TYPE_DEBIT)->sum('amount');

        $cashBalance = (float) PmAccountingEntry::query()
            ->where(function ($q) {
                $q->where('account_name', 'like', '%cash%')
                    ->orWhere('account_name', 'like', '%bank%');
            })
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END),0) as bal", [PmAccountingEntry::TYPE_DEBIT, PmAccountingEntry::TYPE_CREDIT])
            ->value('bal');

        $accountsReceivable = (float) PmInvoice::query()
            ->liveBalances()
            ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as bal')
            ->value('bal');

        $landlordPayable = max(0.0, (float) PmLandlordLedgerEntry::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END),0) as bal")
            ->value('bal'));

        $accountsPayable = (float) PmAccountingEntry::query()
            ->where('account_name', 'like', '%payable%')
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END),0) as bal", [PmAccountingEntry::TYPE_CREDIT, PmAccountingEntry::TYPE_DEBIT])
            ->value('bal');

        $trendStart = now()->startOfMonth()->subMonths(5);
        $trendRows = PmAccountingEntry::query()
            ->whereDate('entry_date', '>=', $trendStart->toDateString())
            ->selectRaw("DATE_FORMAT(entry_date, '%Y-%m') as ym")
            ->selectRaw("COALESCE(SUM(CASE WHEN category = ? AND entry_type = ? THEN amount ELSE 0 END),0) as income_total", [PmAccountingEntry::CATEGORY_INCOME, PmAccountingEntry::TYPE_CREDIT])
            ->selectRaw("COALESCE(SUM(CASE WHEN category = ? AND entry_type = ? THEN amount ELSE 0 END),0) as expense_total", [PmAccountingEntry::CATEGORY_EXPENSE, PmAccountingEntry::TYPE_DEBIT])
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');
        $monthlyTrend = collect(range(0, 5))->map(function ($i) use ($trendStart, $trendRows) {
            $m = $trendStart->copy()->addMonths($i);
            $ym = $m->format('Y-m');
            $row = $trendRows->get($ym);
            return [
                'label' => $m->format('M'),
                'income' => (float) ($row->income_total ?? 0),
                'expense' => (float) ($row->expense_total ?? 0),
            ];
        })->all();

        $rentBilled = (float) PmInvoice::query()
            ->where('invoice_type', PmInvoice::TYPE_RENT)
            ->whereYear('issue_date', now()->year)
            ->whereMonth('issue_date', now()->month)
            ->sum('amount');
        $rentCollected = (float) PmInvoice::query()
            ->where('invoice_type', PmInvoice::TYPE_RENT)
            ->whereYear('issue_date', now()->year)
            ->whereMonth('issue_date', now()->month)
            ->sum('amount_paid');

        $alerts = [
            'overdue_tenants' => (int) PmInvoice::query()->where('status', PmInvoice::STATUS_OVERDUE)->count(),
            'unreconciled_bank' => Schema::hasTable('unassigned_payments') ? (int) UnassignedPayment::query()->count() : 0,
            'pending_payouts' => Schema::hasTable('pm_landlord_payouts') ? (int) PmLandlordPayout::query()->whereIn('status', ['draft', 'approved'])->count() : 0,
            'failed_messages' => Schema::hasTable('pm_message_deliveries') ? (int) PmMessageDelivery::query()->where('status', 'failed')->count() : 0,
            'negative_cash' => $cashBalance < 0 ? 1 : 0,
        ];

        return property_view('property.agent.accounting.index', [
            'stats' => [
                ['label' => 'Cash Balance', 'value' => PropertyMoney::kes($cashBalance), 'hint' => 'Cash & bank'],
                ['label' => 'Accounts Receivable', 'value' => PropertyMoney::kes($accountsReceivable), 'hint' => 'Open tenant balances'],
                ['label' => 'Landlord Payable', 'value' => PropertyMoney::kes($landlordPayable), 'hint' => 'Net owed to landlords'],
                ['label' => 'Accounts Payable', 'value' => PropertyMoney::kes($accountsPayable), 'hint' => 'Supplier and other payables'],
                ['label' => 'Revenue (This Month)', 'value' => PropertyMoney::kes($income), 'hint' => 'Income credits'],
                ['label' => 'Expenses (This Month)', 'value' => PropertyMoney::kes($expenses), 'hint' => 'Expense debits'],
                ['label' => 'Net Income', 'value' => PropertyMoney::kes($income - $expenses), 'hint' => 'Revenue - Expenses'],
            ],
            'monthlyTrend' => $monthlyTrend,
            'rentSnapshot' => ['billed' => $rentBilled, 'collected' => $rentCollected],
            'alerts' => $alerts,
        ]);
    }

    public function entries(Request $request): View
    {
        $user = $request->user();
        $agentUserId = (int) $user->id;
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'source' => trim((string) $request->query('source', '')),
            'status' => trim((string) $request->query('status', '')),
            'account_id' => (int) $request->integer('account_id'),
            'property_id' => (int) $request->integer('property_id'),
        ];

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthBatches = AccountingJournalBatch::query()
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->whereBetween('date', [$monthStart, $monthEnd]);

        $monthBatchIds = (clone $monthBatches)->pluck('id');
        $monthLines = AccountingJournalLine::query()
            ->whereIn('batch_id', $monthBatchIds)
            ->selectRaw('COALESCE(SUM(debit),0) as debit_total, COALESCE(SUM(credit),0) as credit_total')
            ->first();

        $query = AccountingJournalBatch::query()
            ->with(['createdByUser:id,name'])
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            });

        if ($filters['from'] !== '') {
            $query->whereDate('date', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('date', '<=', $filters['to']);
        }
        if ($filters['source'] !== '') {
            if ($filters['source'] === 'manual') {
                $query->where('source_type', 'manual');
            } elseif ($filters['source'] === 'system') {
                $query->where('source_type', '!=', 'manual');
            } else {
                $query->where('source_type', $filters['source']);
            }
        }
        if (in_array($filters['status'], ['posted', 'reversed'], true)) {
            $query->where('status', $filters['status']);
        }
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('description', 'like', '%'.$q.'%')
                    ->orWhere('source_key', 'like', '%'.$q.'%')
                    ->orWhere('id', $q);
            });
        }
        if ($filters['account_id'] > 0) {
            $accountId = $filters['account_id'];
            $query->whereHas('lines', fn ($lq) => $lq->where('account_id', $accountId));
        }
        if ($filters['property_id'] > 0) {
            $propertyId = $filters['property_id'];
            $query->whereHas('lines', fn ($lq) => $lq->where('property_id', $propertyId));
        }

        $paginator = $query->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();
        $batchIds = $paginator->getCollection()->pluck('id')->all();
        $lineSums = AccountingJournalLine::query()
            ->whereIn('batch_id', $batchIds)
            ->selectRaw('batch_id, COALESCE(SUM(debit),0) as debit_total, COALESCE(SUM(credit),0) as credit_total')
            ->groupBy('batch_id')
            ->get()
            ->keyBy('batch_id');

        $rows = $paginator->getCollection()->map(function (AccountingJournalBatch $batch) use ($lineSums) {
            $totals = $lineSums->get((int) $batch->id);
            $debit = (float) ($totals->debit_total ?? 0);
            $credit = (float) ($totals->credit_total ?? 0);
            $source = $this->journalSourceLabel((string) $batch->source_type);
            $statusLabel = (string) $batch->status;
            $viewUrl = route('property.accounting.entries.show', ['batch' => $batch->id], false);
            $exportUrl = route('property.accounting.entries.export', ['q' => 'BATCH#'.$batch->id], false);
            $action = new HtmlString(
                '<div class="flex gap-2">'.
                '<a href="'.$viewUrl.'" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>'.
                (($statusLabel === 'posted' && is_null($batch->reversed_at))
                    ? '<form method="POST" action="'.route('property.accounting.entries.reverse', ['entry' => $batch->id], false).'" data-swal-title="Post reversal?" data-swal-confirm="Post reversal for this journal?" data-swal-confirm-text="Yes, reverse">'.csrf_field().'<button type="submit" class="text-rose-600 hover:text-rose-700 font-medium">Reverse</button></form>'
                    : '<span class="text-slate-500">Reverse</span>').
                '<a href="'.$exportUrl.'" class="text-slate-700 hover:text-slate-900 font-medium">Export</a>'.
                '</div>'
            );

            return [
                $batch->date?->format('Y-m-d') ?? '—',
                'JRN-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT),
                $batch->description ?: '—',
                PropertyMoney::kes($debit),
                PropertyMoney::kes($credit),
                $source,
                ucfirst($statusLabel),
                $batch->createdByUser?->name ?: 'System',
                $action,
            ];
        })->all();

        $mappedSources = [
            'manual' => 'Manual',
            'pm_invoice' => 'Invoice',
            'pm_payment' => 'Payment',
            'pm_maintenance_job' => 'Maintenance',
            'payroll' => 'Payroll',
        ];

        $accounts = AccountingChartAccount::query()
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return property_view('property.agent.accounting.entries', [
            'stats' => [
                ['label' => 'Total Entries (This Month)', 'value' => (string) (clone $monthBatches)->count(), 'hint' => 'Journal batches'],
                ['label' => 'Total Debits', 'value' => PropertyMoney::kes((float) ($monthLines?->debit_total ?? 0)), 'hint' => 'This month'],
                ['label' => 'Total Credits', 'value' => PropertyMoney::kes((float) ($monthLines?->credit_total ?? 0)), 'hint' => 'This month'],
                ['label' => 'Manual Entries Count', 'value' => (string) (clone $monthBatches)->where('source_type', 'manual')->count(), 'hint' => 'This month'],
                ['label' => 'System Entries Count', 'value' => (string) (clone $monthBatches)->where('source_type', '!=', 'manual')->count(), 'hint' => 'This month'],
            ],
            'columns' => ['Date', 'Reference', 'Description', 'Total Debit', 'Total Credit', 'Source', 'Status', 'Created By', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $paginator,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'accounts' => $accounts,
            'accountMap' => PropertyAccountingPostingService::accountMap(),
            'sourceOptions' => $mappedSources,
            'statusOptions' => ['posted' => 'Posted', 'reversed' => 'Reversed'],
            'filters' => $filters,
            'mappingOptions' => $accounts->mapWithKeys(fn ($a) => [(string) $a->name => $a->code.' - '.$a->name])->all(),
        ]);
    }

    public function exportEntriesCsv(Request $request): StreamedResponse
    {
        $agentUserId = (int) $request->user()->id;
        $query = AccountingJournalBatch::query()
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->orderByDesc('date')
            ->orderByDesc('id');
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }
        $source = trim((string) $request->query('source', ''));
        if ($source !== '') {
            if ($source === 'manual') {
                $query->where('source_type', 'manual');
            } elseif ($source === 'system') {
                $query->where('source_type', '!=', 'manual');
            } else {
                $query->where('source_type', $source);
            }
        }
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, ['posted', 'reversed'], true)) {
            $query->where('status', $status);
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('description', 'like', '%'.$q.'%')
                    ->orWhere('source_key', 'like', '%'.$q.'%')
                    ->orWhere('id', $q);
            });
        }

        $rowsData = $query->limit(5000)->get();
        $batchIds = $rowsData->pluck('id')->all();
        $lineTotals = AccountingJournalLine::query()
            ->whereIn('batch_id', $batchIds)
            ->selectRaw('batch_id, COALESCE(SUM(debit),0) as debit_total, COALESCE(SUM(credit),0) as credit_total')
            ->groupBy('batch_id')
            ->get()
            ->keyBy('batch_id');
        $format = strtolower((string) $request->query('format', 'csv'));

        $headers = ['Date', 'Reference', 'Description', 'Total Debit', 'Total Credit', 'Source', 'Status', 'Reversal Link'];
        $rowsClosure = function () use ($rowsData, $lineTotals) {
            foreach ($rowsData as $e) {
                $totals = $lineTotals->get((int) $e->id);
                yield [
                    $e->date?->format('Y-m-d') ?? '',
                    'JRN-'.str_pad((string) $e->id, 6, '0', STR_PAD_LEFT),
                    (string) ($e->description ?? ''),
                    (string) ((float) ($totals->debit_total ?? 0)),
                    (string) ((float) ($totals->credit_total ?? 0)),
                    $this->journalSourceLabel((string) $e->source_type),
                    (string) $e->status,
                    $e->reversed_from_batch_id ? ('reversal_of_'.$e->reversed_from_batch_id) : '',
                ];
            }
        };

        return TabularExport::stream('property-accounting-entries', $headers, $rowsClosure, $format);
    }

    public function auditTrail(Request $request): View
    {
        $query = $this->buildAuditTrailBatchQuery($request);
        $paginator = $query->paginate(50)->withQueryString();
        $rowsData = $paginator->getCollection();
        $batchIds = $rowsData->pluck('id')->all();

        $lineMap = $this->loadBatchLineSummaries($batchIds);
        $reversalChildren = AccountingJournalBatch::query()
            ->whereIn('reversed_from_batch_id', $batchIds)
            ->get(['id', 'reversed_from_batch_id', 'created_at'])
            ->groupBy('reversed_from_batch_id');
        $previewPayload = [];

        $rows = $rowsData->map(function (AccountingJournalBatch $b) use ($lineMap, $reversalChildren, &$previewPayload) {
            $summary = $lineMap->get((int) $b->id, ['impact' => '—', 'impact_html' => '—']);
            $reference = (string) ($b->source_key ?: ($b->source_type.'#'.$b->source_id));
            $userLabel = $b->postedByUser?->name ?: ($b->createdByUser?->name ?: ('User #'.((string) ($b->posted_by ?: $b->created_by ?: '—'))));
            $entity = (string) $b->source_type;
            $sourceType = $this->auditSourceTypeLabel($b);
            $sourceTypeBadge = match ($sourceType) {
                'Manual' => '<span class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Manual</span>',
                'API' => '<span class="inline-flex rounded-full border border-violet-300 bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-700">API</span>',
                'Webhook' => '<span class="inline-flex rounded-full border border-cyan-300 bg-cyan-50 px-2 py-0.5 text-[11px] font-semibold text-cyan-700">Webhook</span>',
                default => '<span class="inline-flex rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">System</span>',
            };
            $action = (string) $b->event_type;
            $childReversals = $reversalChildren->get($b->id, collect());
            $isReversal = ! is_null($b->reversed_from_batch_id) || str_contains(strtolower((string) $b->event_type), 'revers');
            $reversalBadge = '';
            if ($isReversal) {
                $reversalBadge = ' <span class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Reversal</span>';
            } elseif ($childReversals->isNotEmpty()) {
                $reversalBadge = ' <span class="inline-flex rounded-full border border-sky-300 bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700">Has reversal</span>';
            }
            $sourceLink = $this->resolveBatchSourceLink($b);
            $sourceAction = $sourceLink['url']
                ? '<a class="text-slate-700 hover:text-slate-900" href="'.$sourceLink['url'].'">Source</a>'
                : '<span class="text-slate-400">Source</span>';
            $previewPayload[(int) $b->id] = [
                'batch' => 'JRN-'.str_pad((string) $b->id, 6, '0', STR_PAD_LEFT),
                'date' => $b->date?->format('Y-m-d') ?? optional($b->created_at)?->format('Y-m-d') ?? '—',
                'action' => $action,
                'entity' => $entity,
                'reference' => $reference,
                'source_type' => $sourceType,
                'status' => ucfirst((string) $b->status),
                'description' => (string) ($b->description ?: 'No description captured.'),
                'impact' => (string) $summary['impact'],
                'reversal_of' => $b->reversed_from_batch_id ? ('JRN-'.str_pad((string) $b->reversed_from_batch_id, 6, '0', STR_PAD_LEFT)) : null,
                'reversal_count' => $childReversals->count(),
            ];

            return [
                $b->date?->format('Y-m-d') ?? optional($b->created_at)?->format('Y-m-d') ?? '—',
                $userLabel,
                $action,
                $entity,
                $reference,
                new HtmlString($sourceTypeBadge),
                new HtmlString((string) ($summary['impact_html'] ?? e((string) $summary['impact']))),
                new HtmlString(ucfirst((string) $b->status).$reversalBadge),
                new HtmlString('<div class="flex gap-2"><a class="text-indigo-600 hover:text-indigo-700 font-medium" href="'.route('property.accounting.audit_trail.show', ['batch' => $b->id]).'">Trace</a>'.$sourceAction.'<button type="button" data-audit-preview-id="'.$b->id.'" data-row-ignore-click class="text-slate-700 hover:text-slate-900">Preview</button></div>'),
            ];
        })->all();

        $summaryQuery = $this->buildAuditTrailBatchQuery($request);
        $total = (clone $summaryQuery)->count();
        $manual = (clone $summaryQuery)->where(function ($q) {
            $q->where('source_type', 'like', '%manual%')->orWhere('event_type', 'like', '%manual%');
        })->count();
        $system = max(0, $total - $manual);
        $reversals = (clone $summaryQuery)->where(function ($q) {
            $q->where('event_type', 'like', '%revers%')->orWhereNotNull('reversed_from_batch_id');
        })->count();
        $today = (clone $summaryQuery)->whereDate('created_at', now()->toDateString())->count();

        return property_view('property.agent.accounting.audit_trail', [
            'stats' => [
                ['label' => 'Total Activities', 'value' => (string) $total, 'hint' => 'Filtered scope'],
                ['label' => 'Manual Actions', 'value' => (string) $manual, 'hint' => 'User initiated'],
                ['label' => 'System Actions', 'value' => (string) $system, 'hint' => 'Automated/API/webhook'],
                ['label' => 'Reversals', 'value' => (string) $reversals, 'hint' => 'Reverse events'],
                ['label' => "Today's Activity", 'value' => (string) $today, 'hint' => 'Today'],
            ],
            'columns' => ['Date', 'User', 'Action', 'Entity', 'Reference', 'Source Type', 'Financial Impact', 'Status', 'Actions'],
            'tableRows' => $rows,
            'previewPayload' => $previewPayload,
            'paginator' => $paginator,
            'actionTypes' => [
                'invoice_created', 'payment_received', 'payment_allocated', 'maintenance_posted', 'payroll_posted',
                'journal_posted', 'journal_reversed', 'payout_created', 'payout_paid',
            ],
            'entityTypes' => ['pm_invoice', 'pm_payment', 'pm_maintenance_job', 'pm_landlord_payout', 'accounting_manual', 'pm_tenant_deposit'],
            'users' => DB::table('users')->select('id', 'name')->orderBy('name')->limit(300)->get(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'tenants' => PmTenant::query()->orderBy('name')->get(['id', 'name']),
            'accountOptions' => AccountingChartAccount::query()->orderBy('code')->limit(500)->get(['id', 'code', 'name']),
            'filters' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'user_id' => $request->input('user_id'),
                'action_type' => $request->input('action_type'),
                'entity_type' => $request->input('entity_type'),
                'reference' => $request->input('reference'),
                'property_id' => $request->input('property_id'),
                'tenant_id' => $request->input('tenant_id'),
                'account_id' => $request->input('account_id'),
                'source_type' => $request->input('source_type'),
                'q' => $request->input('q'),
            ],
        ]);
    }

    public function exportAuditTrailCsv(Request $request): StreamedResponse
    {
        $rowsData = $this->buildAuditTrailBatchQuery($request)->limit(5000)->get();
        $lineMap = $this->loadBatchLineSummaries($rowsData->pluck('id')->all());
        $format = strtolower((string) $request->query('format', 'csv'));
        $headers = ['Date', 'User', 'Action', 'Entity', 'Reference', 'Source Type', 'Financial Impact', 'Status', 'Reversal Link'];
        $rowsClosure = function () use ($rowsData, $lineMap) {
            foreach ($rowsData as $e) {
                $impact = (string) ($lineMap->get((int) $e->id)['impact'] ?? '—');
                yield [
                    $e->date?->format('Y-m-d') ?? '',
                    $e->postedByUser?->name ?: ($e->createdByUser?->name ?: ''),
                    (string) $e->event_type,
                    (string) $e->source_type,
                    (string) $e->source_key,
                    $this->auditSourceTypeLabel($e),
                    $impact,
                    (string) $e->status,
                    $e->reversed_from_batch_id ? ('reversal_of_'.$e->reversed_from_batch_id) : '',
                ];
            }
        };

        return TabularExport::stream('property-accounting-audit-trail', $headers, $rowsClosure, $format);
    }

    public function auditTrailShow(Request $request, AccountingJournalBatch $batch): View
    {
        $this->ensureAuditBatchScope($request, $batch);
        $batch->load(['lines.structuredAccount', 'createdByUser', 'postedByUser', 'reversedFrom']);

        $lineImpact = $batch->lines->map(function (AccountingJournalLine $line) {
            $account = (string) ($line->structuredAccount?->name ?: 'Account #'.$line->account_id);
            $dr = (float) $line->debit;
            $cr = (float) $line->credit;
            $sign = $dr >= $cr ? '+' : '-';
            $amount = abs($dr - $cr);
            return $account.' '.$sign.PropertyMoney::kes($amount);
        })->values()->all();

        $sourceRecord = $this->resolveAuditSourceRecord($batch);
        $linkedBatches = AccountingJournalBatch::query()
            ->where('source_type', $batch->source_type)
            ->where('source_id', $batch->source_id)
            ->orderByDesc('id')
            ->get();
        $landlordImpact = PmLandlordLedgerEntry::query()
            ->where(function ($q) use ($batch) {
                $q->where('reference_type', $batch->source_type)
                    ->orWhere('reference_type', $batch->source_type.'_reversal')
                    ->orWhere('reference_type', 'pm_payment')
                    ->orWhere('reference_type', 'pm_payment_reversal');
            })
            ->where('reference_id', $batch->source_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $reversalHistory = AccountingJournalBatch::query()
            ->where('id', $batch->id)
            ->orWhere('reversed_from_batch_id', $batch->id)
            ->orWhere('id', $batch->reversed_from_batch_id)
            ->orderBy('id')
            ->get();

        return property_view('property.agent.accounting.audit_trail_show', [
            'batch' => $batch,
            'lineImpact' => $lineImpact,
            'sourceRecord' => $sourceRecord,
            'linkedBatches' => $linkedBatches,
            'landlordImpact' => $landlordImpact,
            'reversalHistory' => $reversalHistory,
        ]);
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
        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->date('to'));
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
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:3000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounting_chart_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $agentUserId = (int) $request->user()->id;
        $lines = collect($data['lines'] ?? [])->map(function (array $line) {
            return [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'description' => trim((string) ($line['description'] ?? '')),
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
            ];
        })->values();

        $accountIds = $lines->pluck('account_id')->all();
        $accounts = AccountingChartAccount::query()
            ->whereIn('id', $accountIds)
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->get()
            ->keyBy('id');

        if ($accounts->count() !== count(array_unique($accountIds))) {
            return back()->withErrors(['lines' => 'One or more selected accounts are outside your workspace.'])->withInput();
        }
        if ($accounts->contains(fn (AccountingChartAccount $a) => ! $a->is_active)) {
            return back()->withErrors(['lines' => 'Disabled accounts cannot be posted.'])->withInput();
        }

        $period = AccountingPeriod::query()
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->whereDate('start_date', '<=', $data['entry_date'])
            ->whereDate('end_date', '>=', $data['entry_date'])
            ->orderByDesc('id')
            ->first();
        if ($period && in_array((string) $period->status, [AccountingPeriod::STATUS_CLOSED, AccountingPeriod::STATUS_LOCKED], true)) {
            return back()->withErrors(['entry_date' => 'This accounting period is locked/closed.'])->withInput();
        }

        $totals = $lines->reduce(function (array $carry, array $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                $carry['invalid'] = true;
            }
            if ($debit < 0 || $credit < 0) {
                $carry['invalid'] = true;
            }
            $carry['debit'] += $debit;
            $carry['credit'] += $credit;
            return $carry;
        }, ['debit' => 0.0, 'credit' => 0.0, 'invalid' => false]);

        if ($totals['invalid']) {
            return back()->withErrors(['lines' => 'Each line must have exactly one side (debit or credit) and positive amount.'])->withInput();
        }
        if (abs(((float) $totals['debit']) - ((float) $totals['credit'])) > 0.0001) {
            return back()->withErrors(['lines' => 'Journal is unbalanced: total debit must equal total credit.'])->withInput();
        }

        $sourceKey = 'manual:'.now()->format('YmdHis').':'.$request->user()->id.':'.substr(md5((string) microtime(true)), 0, 8);
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'JRN-'.now()->format('Ymd-His');
        }

        DB::transaction(function () use ($data, $request, $lines, $accounts, $agentUserId, $sourceKey, $reference): void {
            $batch = AccountingJournalBatch::query()->create([
                'date' => $data['entry_date'],
                'description' => (string) $data['description'],
                'source_type' => 'manual',
                'source_id' => 0,
                'event_type' => 'manual_entry',
                'source_key' => $sourceKey,
                'status' => AccountingJournalBatch::STATUS_POSTED,
                'agent_user_id' => $agentUserId,
                'created_by' => $request->user()->id,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $account = $accounts->get($line['account_id']);
                AccountingJournalLine::query()->create([
                    'batch_id' => $batch->id,
                    'account_id' => $account->id,
                    'accounting_chart_account_id' => $account->id,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'memo' => $line['description'] !== '' ? $line['description'] : (string) $data['description'],
                    'reference' => $reference,
                    'property_id' => $data['property_id'] ?: null,
                    'agent_user_id' => $agentUserId,
                ]);

                PmAccountingEntry::query()->create([
                    'property_id' => $data['property_id'] ?: null,
                    'recorded_by_user_id' => $request->user()->id,
                    'entry_date' => $data['entry_date'],
                    'account_name' => (string) $account->name,
                    'category' => (string) (($account->type ?: $account->account_type) ?: PmAccountingEntry::CATEGORY_EXPENSE),
                    'entry_type' => ((float) $line['debit']) > 0 ? PmAccountingEntry::TYPE_DEBIT : PmAccountingEntry::TYPE_CREDIT,
                    'amount' => ((float) $line['debit']) > 0 ? (float) $line['debit'] : (float) $line['credit'],
                    'reference' => $reference,
                    'description' => (string) $data['description'],
                    'source_key' => 'manual_entry',
                ]);
            }
        });

        return back()->with('success', 'Journal entry posted.');
    }

    public function reverseEntry(Request $request, int $entry): RedirectResponse
    {
        $batch = AccountingJournalBatch::query()->with('lines')->findOrFail($entry);
        if ((int) ($batch->agent_user_id ?? 0) !== (int) $request->user()->id) {
            abort(403);
        }
        if ($batch->status !== AccountingJournalBatch::STATUS_POSTED) {
            return back()->withErrors(['entry' => 'Only posted journals can be reversed.']);
        }
        if (! is_null($batch->reversed_at)) {
            return back()->withErrors(['entry' => 'This journal is already reversed.']);
        }
        $alreadyReversed = AccountingJournalBatch::query()
            ->where('reversed_from_batch_id', $batch->id)
            ->exists();
        if ($alreadyReversed) {
            return back()->withErrors(['entry' => 'This journal is already reversed.']);
        }
        $period = AccountingPeriod::query()
            ->where(function ($q) use ($request) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $request->user()->id);
            })
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderByDesc('id')
            ->first();
        if ($period && in_array((string) $period->status, [AccountingPeriod::STATUS_CLOSED, AccountingPeriod::STATUS_LOCKED], true)) {
            return back()->withErrors(['entry' => 'Current accounting period is locked/closed.']);
        }

        DB::transaction(function () use ($request, $batch): void {
            $newSourceKey = 'manual_reversal:batch:'.$batch->id.':'.now()->format('YmdHis');
            $reversal = AccountingJournalBatch::query()->create([
                'date' => now()->toDateString(),
                'description' => 'Reversal of journal #'.$batch->id,
                'source_type' => 'manual',
                'source_id' => $batch->id,
                'event_type' => 'manual_reversal',
                'source_key' => $newSourceKey,
                'status' => AccountingJournalBatch::STATUS_POSTED,
                'agent_user_id' => $request->user()->id,
                'created_by' => $request->user()->id,
                'posted_by' => $request->user()->id,
                'reversed_from_batch_id' => $batch->id,
                'posted_at' => now(),
            ]);

            foreach ($batch->lines as $line) {
                $accountName = (string) optional(AccountingChartAccount::query()->find($line->account_id ?: $line->accounting_chart_account_id))->name;
                AccountingJournalLine::query()->create([
                    'batch_id' => $reversal->id,
                    'account_id' => $line->account_id ?: $line->accounting_chart_account_id,
                    'accounting_chart_account_id' => $line->account_id ?: $line->accounting_chart_account_id,
                    'debit' => (float) $line->credit,
                    'credit' => (float) $line->debit,
                    'memo' => 'Reversal: '.(string) ($line->memo ?? ''),
                    'reference' => 'REV-'.((string) ($line->reference ?: ('BATCH-'.$batch->id))),
                    'property_id' => $line->property_id,
                    'tenant_id' => $line->tenant_id,
                    'landlord_id' => $line->landlord_id,
                    'unit_id' => $line->unit_id,
                    'agent_user_id' => $request->user()->id,
                ]);

                if ($accountName !== '') {
                    PmAccountingEntry::query()->create([
                        'property_id' => $line->property_id,
                        'recorded_by_user_id' => $request->user()->id,
                        'entry_date' => now()->toDateString(),
                        'account_name' => $accountName,
                        'category' => $this->categoryFromAccountName($accountName),
                        'entry_type' => ((float) $line->credit) > 0 ? PmAccountingEntry::TYPE_DEBIT : PmAccountingEntry::TYPE_CREDIT,
                        'amount' => ((float) $line->credit) > 0 ? (float) $line->credit : (float) $line->debit,
                        'reference' => 'REV-'.((string) ($line->reference ?: ('BATCH-'.$batch->id))),
                        'description' => 'Reversal of journal #'.$batch->id,
                        'source_key' => 'manual_reversal',
                    ]);
                }
            }

            $batch->status = AccountingJournalBatch::STATUS_REVERSED;
            $batch->reversed_by = $request->user()->id;
            $batch->reversed_at = now();
            $batch->save();
        });

        return back()->with('success', 'Journal reversed successfully.');
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
            'accounts_receivable' => ['required', 'integer', 'exists:accounting_chart_accounts,id'],
            'rental_income' => ['required', 'integer', 'exists:accounting_chart_accounts,id'],
            'cash_bank' => ['required', 'integer', 'exists:accounting_chart_accounts,id'],
            'maintenance_expense' => ['required', 'integer', 'exists:accounting_chart_accounts,id'],
            'accounts_payable' => ['required', 'integer', 'exists:accounting_chart_accounts,id'],
        ]);

        $agentUserId = (int) $request->user()->id;
        $accounts = AccountingChartAccount::query()
            ->whereIn('id', array_values($data))
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->get()
            ->keyBy('id');
        if ($accounts->count() !== count(array_unique(array_values($data)))) {
            return back()->withErrors(['accounts_receivable' => 'Some selected accounts are outside your workspace.']);
        }
        if ($accounts->contains(fn (AccountingChartAccount $a) => ! $a->is_active)) {
            return back()->withErrors(['accounts_receivable' => 'Mappings must point to active accounts only.']);
        }

        $payload = [];
        foreach ($data as $key => $id) {
            $payload[$key] = (string) ($accounts->get((int) $id)?->name ?? '');
        }

        PropertyPortalSetting::query()->updateOrCreate(
            ['key' => 'property_accounting_account_map'],
            ['value' => json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );

        return back()->with('success', 'Account mapping saved.');
    }

    public function showEntry(Request $request, AccountingJournalBatch $batch): View
    {
        $agentUserId = (int) $request->user()->id;
        $owned = (int) $batch->agent_user_id === $agentUserId;
        abort_unless($owned, 403);

        $batch->load([
            'createdByUser:id,name',
            'postedByUser:id,name',
            'lines.structuredAccount:id,code,name',
        ]);

        $totalDebit = (float) $batch->lines->sum(fn (AccountingJournalLine $line) => (float) $line->debit);
        $totalCredit = (float) $batch->lines->sum(fn (AccountingJournalLine $line) => (float) $line->credit);
        $linkedReverse = AccountingJournalBatch::query()
            ->where('reversed_from_batch_id', $batch->id)
            ->first();

        return property_view('property.agent.accounting.entry_show', [
            'batch' => $batch,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'linkedReverse' => $linkedReverse,
            'sourceLabel' => $this->journalSourceLabel((string) $batch->source_type),
        ]);
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

        return property_view('property.agent.accounting.reports.trial_balance', [
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

        return property_view('property.agent.accounting.reports.income_statement', [
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
                $e->description ?: '—',
                PropertyMoney::kes($debit),
                PropertyMoney::kes($credit),
                PropertyMoney::kes($running),
                $e->reference ?: '—',
            ];
        })->all();

        $paginator = $this->paginateCollection($request, $rows, 50);

        return property_view('property.agent.accounting.reports.cash_book', [
            'columns' => ['Date', 'Description', 'Debit', 'Credit', 'Balance', 'Reference'],
            'tableRows' => $paginator->items(),
            'stats' => [
                ['label' => 'Rows', 'value' => (string) count($rows), 'hint' => 'Cash/Bank records'],
            ],
            'paginator' => $paginator,
            'filters' => ['from' => $request->input('from'), 'to' => $request->input('to'), 'q' => $request->input('q')],
        ]);
    }

    public function payroll(Request $request): View
    {
        $user = $request->user();
        $agentUserId = (int) $user->id;
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $filters = [
            'period' => trim((string) $request->query('period', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'employee_id' => (int) $request->integer('employee_id'),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $runsQuery = AccountingPayrollPeriod::query()
            ->with(['createdByUser:id,name', 'postedByUser:id,name'])
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            });

        if ($filters['period'] !== '' && preg_match('/^\d{4}-\d{2}$/', $filters['period'])) {
            [$y, $m] = array_map('intval', explode('-', $filters['period']));
            $runsQuery->where('period_year', $y)->where('period_month', $m);
        }
        if (in_array($filters['status'], ['draft', 'approved', 'posted', 'reversed'], true)) {
            $runsQuery->where('status', $filters['status']);
        }
        if ($filters['from'] !== '') {
            $runsQuery->whereDate('period_start', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $runsQuery->whereDate('period_end', '<=', $filters['to']);
        }
        if ($filters['employee_id'] > 0) {
            $runsQuery->whereHas('lines', fn ($q) => $q->where('employee_id', $filters['employee_id']));
        }

        $paginator = $runsQuery->orderByDesc('period_year')->orderByDesc('period_month')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $baseScope = AccountingPayrollPeriod::query()->where(function ($q) use ($agentUserId) {
            $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
        });
        $thisMonthScope = (clone $baseScope)->whereBetween('period_start', [$monthStart, $monthEnd]);
        $employeesPaidThisMonth = AccountingPayrollLine::query()
            ->whereHas('period', function ($q) use ($agentUserId, $monthStart, $monthEnd) {
                $q->where(function ($sq) use ($agentUserId) {
                    $sq->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
                })->whereBetween('period_start', [$monthStart, $monthEnd]);
            })
            ->distinct('employee_id')
            ->count('employee_id');

        $rows = $paginator->getCollection()->map(function (AccountingPayrollPeriod $run) {
            $actions = [];
            $actions[] = '<a class="text-indigo-600 hover:text-indigo-700 font-medium" href="'.route('property.accounting.payroll.show', ['period' => $run->id]).'">View run</a>';
            if ($run->status === AccountingPayrollPeriod::STATUS_DRAFT) {
                $actions[] = '<form method="post" action="'.route('property.accounting.payroll.approve', ['period' => $run->id]).'" style="display:inline;">'.csrf_field().'<button class="text-emerald-700 hover:text-emerald-800 font-medium" type="submit">Approve run</button></form>';
            }
            if ($run->status === AccountingPayrollPeriod::STATUS_APPROVED) {
                $actions[] = '<form method="post" action="'.route('property.accounting.payroll.post', ['period' => $run->id]).'" style="display:inline;">'.csrf_field().'<button class="text-blue-700 hover:text-blue-800 font-medium" type="submit">Post to accounting</button></form>';
            }
            if ($run->status === AccountingPayrollPeriod::STATUS_POSTED) {
                $actions[] = '<form method="post" action="'.route('property.accounting.payroll.reverse', ['period' => $run->id]).'" style="display:inline;">'.csrf_field().'<button class="text-rose-700 hover:text-rose-800 font-medium" type="submit">Reverse</button></form>';
            }
            $actions[] = '<a class="text-slate-700 hover:text-slate-900 font-medium" href="'.route('property.accounting.payroll.export', ['period' => $run->id]).'">Export</a>';
            return [
                '#'.$run->id,
                $run->label ?: ($run->period_start?->format('M Y') ?? '—'),
                PropertyMoney::kes((float) $run->total_gross),
                PropertyMoney::kes((float) $run->total_deductions),
                PropertyMoney::kes((float) $run->total_net),
                ucfirst((string) $run->status),
                $run->createdByUser?->name ?? '—',
                $run->posted_at?->format('Y-m-d H:i') ?? '—',
                new HtmlString(implode(' <span class="text-slate-300">|</span> ', $actions)),
            ];
        })->all();

        return property_view('property.agent.accounting.payroll.index', [
            'stats' => [
                ['label' => 'Employees Paid (this month)', 'value' => (string) $employeesPaidThisMonth, 'hint' => 'Distinct employees'],
                ['label' => 'Total Payroll (this month)', 'value' => PropertyMoney::kes((float) (clone $thisMonthScope)->sum('total_gross')), 'hint' => 'Gross payroll'],
                ['label' => 'Pending Payroll Runs', 'value' => (string) (clone $baseScope)->whereIn('status', ['draft', 'approved'])->count(), 'hint' => 'Awaiting posting'],
                ['label' => 'Posted Payroll Runs', 'value' => (string) (clone $baseScope)->where('status', 'posted')->count(), 'hint' => 'Finalized'],
                ['label' => 'Payroll Expense (this month)', 'value' => PropertyMoney::kes((float) (clone $thisMonthScope)->sum('total_gross')), 'hint' => 'Debited to expense'],
            ],
            'columns' => ['Run ID', 'Period', 'Total Gross', 'Total Deductions', 'Net Pay', 'Status', 'Created By', 'Posted At', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $paginator,
            'employees' => Employee::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']),
            'runOptions' => (clone $baseScope)->orderByDesc('period_year')->orderByDesc('period_month')->limit(24)->get(['id', 'label', 'status']),
            'filters' => $filters,
        ]);
    }

    public function payrollStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        $agentUserId = (int) $user->id;
        $data = $request->validate([
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2200'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'action' => ['required', 'in:save_draft,approve,post'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'lines.*.basic_pay' => ['required', 'numeric', 'min:0'],
            'lines.*.allowances' => ['nullable', 'numeric', 'min:0'],
            'lines.*.deductions' => ['nullable', 'numeric', 'min:0'],
        ]);

        $periodMonth = (int) $data['period_month'];
        $periodYear = (int) $data['period_year'];
        $periodStart = Carbon::create($periodYear, $periodMonth, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $existing = AccountingPayrollPeriod::query()
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->where('period_month', $periodMonth)
            ->where('period_year', $periodYear)
            ->first();
        if ($existing && in_array((string) $existing->status, [AccountingPayrollPeriod::STATUS_POSTED, AccountingPayrollPeriod::STATUS_REVERSED], true)) {
            return back()->withErrors(['period_month' => 'This payroll period is already posted/reversed. Create a new period only by reversal workflow.'])->withInput();
        }

        $totals = ['gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0];
        $rows = [];
        foreach ($data['lines'] as $line) {
            $basic = (float) ($line['basic_pay'] ?? 0);
            $allowances = (float) ($line['allowances'] ?? 0);
            $deductions = (float) ($line['deductions'] ?? 0);
            $gross = $basic + $allowances;
            $net = $gross - $deductions;
            if ($gross <= 0 || $net < 0) {
                return back()->withErrors(['lines' => 'Each line must have positive gross and non-negative net pay.'])->withInput();
            }
            $rows[] = [
                'employee_id' => (int) $line['employee_id'],
                'basic_pay' => $basic,
                'allowances' => $allowances,
                'gross_pay' => $gross,
                'deductions' => $deductions,
                'net_pay' => $net,
            ];
            $totals['gross'] += $gross;
            $totals['deductions'] += $deductions;
            $totals['net'] += $net;
        }

        $targetStatus = match ((string) $data['action']) {
            'approve' => AccountingPayrollPeriod::STATUS_APPROVED,
            'post' => AccountingPayrollPeriod::STATUS_APPROVED,
            default => AccountingPayrollPeriod::STATUS_DRAFT,
        };

        $period = DB::transaction(function () use ($existing, $agentUserId, $user, $periodStart, $periodEnd, $periodMonth, $periodYear, $data, $rows, $totals, $targetStatus) {
            $period = $existing ?: new AccountingPayrollPeriod();
            if ($period->exists && ! in_array($period->status, [AccountingPayrollPeriod::STATUS_DRAFT, AccountingPayrollPeriod::STATUS_APPROVED], true)) {
                abort(422, 'Posted/reversed payroll runs are not editable.');
            }
            $period->fill([
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'period_month' => $periodMonth,
                'period_year' => $periodYear,
                'label' => $periodStart->format('F Y'),
                'status' => $targetStatus,
                'notes' => $data['notes'] ?? null,
                'total_gross' => $totals['gross'],
                'total_deductions' => $totals['deductions'],
                'total_net' => $totals['net'],
                'agent_user_id' => $agentUserId,
                'created_by' => $period->created_by ?: $user->id,
            ]);
            if ($targetStatus === AccountingPayrollPeriod::STATUS_APPROVED) {
                $period->approved_by = $user->id;
                $period->approved_at = now();
            }
            $period->save();

            $period->lines()->delete();
            foreach ($rows as $line) {
                $period->lines()->create([
                    ...$line,
                    'payslip_number' => 'PSL-'.$periodYear.str_pad((string) $periodMonth, 2, '0', STR_PAD_LEFT).'-'.$line['employee_id'],
                ]);
            }

            return $period->fresh(['lines']);
        });

        if ((string) $data['action'] === 'post') {
            return $this->postPayrollRun($request, $period);
        }

        return redirect()->route('property.accounting.payroll.show', ['period' => $period->id])->with('success', $targetStatus === AccountingPayrollPeriod::STATUS_APPROVED ? 'Payroll run saved and approved.' : 'Payroll run saved as draft.');
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

    public function payrollPayslips(Request $request): View
    {
        $agentUserId = (int) ($request->user()->id ?? 0);
        $filters = [
            'period' => trim((string) $request->query('period', '')),
            'employee_id' => (int) $request->integer('employee_id'),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'payroll_run_id' => (int) $request->integer('payroll_run_id'),
            'payment_status' => strtolower(trim((string) $request->query('payment_status', ''))),
            'q' => trim($request->string('q')->toString()),
        ];

        $query = AccountingPayrollLine::query()
            ->with(['employee', 'period.journalBatch'])
            ->whereHas('period', function ($q) use ($agentUserId) {
                $q->where(function ($sq) use ($agentUserId) {
                    $sq->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
                });
            })
            ->when($filters['period'] !== '' && preg_match('/^\d{4}-\d{2}$/', $filters['period']), function ($q) use ($filters) {
                [$year, $month] = array_map('intval', explode('-', $filters['period']));
                $q->whereHas('period', fn ($pq) => $pq->where('period_year', $year)->where('period_month', $month));
            })
            ->when($filters['employee_id'] > 0, fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when($filters['payroll_run_id'] > 0, fn ($q) => $q->where('accounting_payroll_period_id', $filters['payroll_run_id']))
            ->when(in_array($filters['payment_status'], ['paid', 'unpaid'], true), fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->when(in_array($filters['status'], ['draft', 'approved', 'posted', 'paid', 'reversed'], true), function ($q) use ($filters) {
                if ($filters['status'] === 'paid') {
                    $q->where('payment_status', 'paid')
                        ->whereHas('period', fn ($pq) => $pq->where('status', AccountingPayrollPeriod::STATUS_POSTED));
                    return;
                }
                $q->whereHas('period', fn ($pq) => $pq->where('status', $filters['status']));
            })
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $s = $filters['q'];
                $q->where(function ($sq) use ($s) {
                    $sq->where('payslip_number', 'like', '%'.$s.'%')
                        ->orWhere('payment_reference', 'like', '%'.$s.'%')
                        ->orWhereHas('employee', function ($eq) use ($s) {
                            $eq->whereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) like ?", ['%'.$s.'%'])
                                ->orWhere('email', 'like', '%'.$s.'%');
                        });
                });
            })
            ->orderByDesc('id');

        $summaryBase = clone $query;
        $paginator = $query->paginate(50)->withQueryString();
        $rows = $paginator->getCollection()->map(function (AccountingPayrollLine $line) {
            $period = $line->period;
            $derivedStatus = (string) ($period?->status ?? 'draft');
            if ($derivedStatus === AccountingPayrollPeriod::STATUS_POSTED && (string) $line->payment_status === 'paid') {
                $derivedStatus = 'paid';
            }
            $journalBatchCell = $period?->journal_batch_id
                ? new HtmlString('<a class="text-indigo-600 hover:text-indigo-700 font-medium" href="'.route('property.accounting.entries.show', ['batch' => $period->journal_batch_id]).'">#'.$period->journal_batch_id.'</a>')
                : '—';
            $actionBits = [
                '<a class="text-indigo-600 hover:text-indigo-700 font-medium" href="'.route('property.accounting.payroll.lines.payslip.show', ['period' => $line->accounting_payroll_period_id, 'line' => $line->id]).'">View</a>',
                '<a class="text-slate-700 hover:text-slate-900 font-medium" href="'.route('property.accounting.payroll.lines.payslip.download', ['period' => $line->accounting_payroll_period_id, 'line' => $line->id]).'">Download PDF</a>',
            ];
            if ($period?->journal_batch_id) {
                $actionBits[] = '<a class="text-blue-700 hover:text-blue-800 font-medium" href="'.route('property.accounting.entries.show', ['batch' => $period->journal_batch_id]).'">View Journal</a>';
            }
            if ((string) $period?->status === AccountingPayrollPeriod::STATUS_POSTED) {
                if ((string) $line->payment_status !== 'paid') {
                    $actionBits[] = '<form method="post" action="'.route('property.accounting.payroll.lines.payment.update', ['period' => $line->accounting_payroll_period_id, 'line' => $line->id]).'" style="display:inline-flex;gap:4px;align-items:center;">'
                        .csrf_field()
                        .'<input type="hidden" name="payment_status" value="paid" />'
                        .'<input type="date" name="payment_date" value="'.now()->toDateString().'" class="rounded border border-slate-200 px-1 py-0.5 text-xs" />'
                        .'<input type="text" name="payment_reference" placeholder="Ref" class="rounded border border-slate-200 px-1 py-0.5 text-xs w-24" />'
                        .'<button class="text-emerald-700 hover:text-emerald-800 font-medium" type="submit">Mark paid</button></form>';
                } else {
                    $actionBits[] = '<form method="post" action="'.route('property.accounting.payroll.lines.payment.update', ['period' => $line->accounting_payroll_period_id, 'line' => $line->id]).'" style="display:inline;">'
                        .csrf_field()
                        .'<input type="hidden" name="payment_status" value="unpaid" />'
                        .'<button class="text-amber-700 hover:text-amber-800 font-medium" type="submit">Mark unpaid</button></form>';
                }
                $actionBits[] = '<form method="post" action="'.route('property.accounting.payroll.reverse', ['period' => $line->accounting_payroll_period_id]).'" style="display:inline;">'.csrf_field().'<button class="text-rose-700 hover:text-rose-800 font-medium" type="submit">Reverse</button></form>';
            }
            return [
                (string) ($line->employee?->full_name ?: 'Employee #'.$line->employee_id),
                (string) ($period?->label ?: '—'),
                PropertyMoney::kes((float) $line->gross_pay),
                PropertyMoney::kes((float) $line->deductions),
                PropertyMoney::kes((float) $line->net_pay),
                ucfirst($derivedStatus),
                '#'.(string) ($line->accounting_payroll_period_id),
                $journalBatchCell,
                (string) ((string) $line->payment_status === 'paid'
                    ? ('Paid'.($line->payment_date ? ' '.$line->payment_date->format('Y-m-d') : '').($line->payment_reference ? ' ('.$line->payment_reference.')' : ''))
                    : 'Unpaid'),
                new HtmlString(implode(' <span class="text-slate-300">|</span> ', $actionBits)),
            ];
        })->all();

        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);
        $runOptions = AccountingPayrollPeriod::query()
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(60)
            ->get(['id', 'label', 'status']);

        return property_view('property.agent.accounting.payroll.payslips', [
            'stats' => [
                ['label' => 'Total Payslips', 'value' => (string) (clone $summaryBase)->count(), 'hint' => 'Filtered result'],
                ['label' => 'Total Gross', 'value' => PropertyMoney::kes((float) (clone $summaryBase)->sum('gross_pay')), 'hint' => 'Payroll gross'],
                ['label' => 'Total Deductions', 'value' => PropertyMoney::kes((float) (clone $summaryBase)->sum('deductions')), 'hint' => 'Statutory/other'],
                ['label' => 'Total Net', 'value' => PropertyMoney::kes((float) (clone $summaryBase)->sum('net_pay')), 'hint' => 'Payable to staff'],
                ['label' => 'Posted Payslips', 'value' => (string) (clone $summaryBase)->whereHas('period', fn ($q) => $q->where('status', AccountingPayrollPeriod::STATUS_POSTED))->count(), 'hint' => 'Linked to journals'],
                ['label' => 'Paid Payslips', 'value' => (string) (clone $summaryBase)->where('payment_status', 'paid')->count(), 'hint' => 'Cash settled'],
            ],
            'columns' => ['Employee', 'Period', 'Gross', 'Deductions', 'Net', 'Status', 'Payroll Run', 'Journal Batch', 'Payment Status', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $paginator,
            'filters' => $filters,
            'employees' => $employees,
            'runOptions' => $runOptions,
        ]);
    }

    public function exportPayrollPayslipsCsv(Request $request): StreamedResponse
    {
        $agentUserId = (int) ($request->user()->id ?? 0);
        $items = AccountingPayrollLine::query()
            ->with(['employee', 'period'])
            ->whereHas('period', function ($q) use ($agentUserId) {
                $q->where(function ($sq) use ($agentUserId) {
                    $sq->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
                });
            })
            ->orderByDesc('id')
            ->limit(5000)
            ->get();
        $format = strtolower((string) $request->query('format', 'csv'));
        $headers = ['Payslip', 'Employee', 'Period', 'Gross', 'Deductions', 'Net', 'Run Status', 'Payment Status', 'Payment Date', 'Payment Reference'];
        $rowsClosure = function () use ($items) {
            foreach ($items as $line) {
                yield [
                    (string) ($line->payslip_number ?: ('PSL-'.$line->accounting_payroll_period_id.'-'.$line->id)),
                    (string) ($line->employee?->full_name ?: 'Employee #'.$line->employee_id),
                    (string) ($line->period?->label ?: ''),
                    (string) $line->gross_pay,
                    (string) $line->deductions,
                    (string) $line->net_pay,
                    (string) ($line->period?->status ?: 'draft'),
                    (string) ($line->payment_status ?: 'unpaid'),
                    $line->payment_date?->format('Y-m-d') ?? '',
                    (string) ($line->payment_reference ?? ''),
                ];
            }
        };

        return TabularExport::stream('property-accounting-payroll-payslip-ledger', $headers, $rowsClosure, $format);
    }

    public function payrollSettings(): View
    {
        $raw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

        return property_view('property.agent.accounting.payroll.settings', [
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

    public function payrollShow(Request $request, AccountingPayrollPeriod $period): View
    {
        $this->ensurePayrollScope($request, $period);
        $period->load(['lines.employee', 'createdByUser:id,name', 'approvedByUser:id,name', 'postedByUser:id,name', 'reversedByUser:id,name']);

        return property_view('property.agent.accounting.payroll.show', [
            'period' => $period,
            'totals' => [
                'gross' => (float) $period->total_gross,
                'deductions' => (float) $period->total_deductions,
                'net' => (float) $period->total_net,
            ],
        ]);
    }

    public function payrollApprove(Request $request, AccountingPayrollPeriod $period): RedirectResponse
    {
        $this->ensurePayrollScope($request, $period);
        if ($period->status !== AccountingPayrollPeriod::STATUS_DRAFT) {
            return back()->withErrors(['payroll' => 'Only draft payroll runs can be approved.']);
        }
        $period->forceFill([
            'status' => AccountingPayrollPeriod::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        return back()->with('success', 'Payroll run approved.');
    }

    public function payrollPost(Request $request, AccountingPayrollPeriod $period): RedirectResponse
    {
        $this->ensurePayrollScope($request, $period);
        return $this->postPayrollRun($request, $period);
    }

    public function payrollReverse(Request $request, AccountingPayrollPeriod $period): RedirectResponse
    {
        $this->ensurePayrollScope($request, $period);
        if ($period->status !== AccountingPayrollPeriod::STATUS_POSTED || ! $period->journal_batch_id) {
            return back()->withErrors(['payroll' => 'Only posted payroll runs can be reversed.']);
        }
        if ($period->reversal_journal_batch_id) {
            return back()->withErrors(['payroll' => 'Payroll run already reversed.']);
        }
        $batch = AccountingJournalBatch::query()->with('lines')->findOrFail($period->journal_batch_id);

        DB::transaction(function () use ($request, $period, $batch): void {
            $reverse = AccountingJournalBatch::query()->create([
                'date' => now()->toDateString(),
                'description' => 'Payroll reversal for run #'.$period->id,
                'source_type' => 'payroll',
                'source_id' => $period->id,
                'event_type' => 'payroll_reversal',
                'source_key' => 'payroll_run_reversal',
                'status' => AccountingJournalBatch::STATUS_POSTED,
                'agent_user_id' => $request->user()->id,
                'created_by' => $request->user()->id,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
                'reversed_from_batch_id' => $batch->id,
            ]);
            foreach ($batch->lines as $line) {
                AccountingJournalLine::query()->create([
                    'batch_id' => $reverse->id,
                    'date' => now()->toDateString(),
                    'account_id' => $line->account_id ?: $line->accounting_chart_account_id,
                    'accounting_chart_account_id' => $line->accounting_chart_account_id ?: $line->account_id,
                    'description' => 'Reversal: '.($line->description ?: 'Payroll run #'.$period->id),
                    'debit' => (float) $line->credit,
                    'credit' => (float) $line->debit,
                    'reference' => 'PAY-REV-'.$period->id,
                ]);
            }

            $this->mirrorPayrollToPmEntries(
                $request,
                (float) $period->total_gross,
                (float) $period->total_deductions,
                (float) $period->total_net,
                now()->toDateString(),
                'PAY-REV-'.$period->id,
                true
            );

            $period->forceFill([
                'status' => AccountingPayrollPeriod::STATUS_REVERSED,
                'reversed_by' => $request->user()->id,
                'reversed_at' => now(),
                'reversal_journal_batch_id' => $reverse->id,
            ])->save();
        });

        return back()->with('success', 'Payroll run reversed with linked journal reversal.');
    }

    public function payrollExport(Request $request, AccountingPayrollPeriod $period): StreamedResponse
    {
        $this->ensurePayrollScope($request, $period);
        $period->load('lines.employee');
        $headers = ['Employee', 'Basic', 'Allowances', 'Gross', 'Deductions', 'Net'];

        return TabularExport::stream('payroll-run-'.$period->id, $headers, function () use ($period) {
            foreach ($period->lines as $line) {
                yield [
                    (string) ($line->employee?->full_name ?: 'Employee #'.$line->employee_id),
                    (string) $line->basic_pay,
                    (string) $line->allowances,
                    (string) $line->gross_pay,
                    (string) $line->deductions,
                    (string) $line->net_pay,
                ];
            }
        }, 'csv');
    }

    public function payrollLinePayslipShow(Request $request, AccountingPayrollPeriod $period, AccountingPayrollLine $line): View
    {
        $this->ensurePayrollScope($request, $period);
        abort_unless((int) $line->accounting_payroll_period_id === (int) $period->id, 404);
        $line->load('employee');

        [$companyName, $logoUrl, $entries] = $this->buildRunPayslipPayload($period, $line);

        return property_view('property.agent.accounting.payroll.payslip', [
            'reference' => $line->payslip_number ?: ('PSL-'.$period->id.'-'.$line->id),
            'entryDate' => $period->period_end?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'employeeName' => (string) ($line->employee?->full_name ?: 'Employee #'.$line->employee_id),
            'companyName' => $companyName,
            'companyLogoUrl' => $logoUrl,
            'basicPay' => (float) $line->basic_pay,
            'allowances' => (float) $line->allowances,
            'grossPay' => (float) $line->gross_pay,
            'deductions' => (float) $line->deductions,
            'netPay' => (float) $line->net_pay,
            'entries' => $entries,
        ]);
    }

    public function payrollLinePayslipDownload(Request $request, AccountingPayrollPeriod $period, AccountingPayrollLine $line): StreamedResponse
    {
        $this->ensurePayrollScope($request, $period);
        abort_unless((int) $line->accounting_payroll_period_id === (int) $period->id, 404);
        $line->load('employee');
        [$companyName, $logoUrl, $entries] = $this->buildRunPayslipPayload($period, $line);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('property.agent.accounting.payroll.payslip', [
            'reference' => $line->payslip_number ?: ('PSL-'.$period->id.'-'.$line->id),
            'entryDate' => $period->period_end?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'employeeName' => (string) ($line->employee?->full_name ?: 'Employee #'.$line->employee_id),
            'companyName' => $companyName,
            'companyLogoUrl' => $logoUrl,
            'basicPay' => (float) $line->basic_pay,
            'allowances' => (float) $line->allowances,
            'grossPay' => (float) $line->gross_pay,
            'deductions' => (float) $line->deductions,
            'netPay' => (float) $line->net_pay,
            'entries' => $entries,
        ]);

        return response()->streamDownload(
            static fn () => print($pdf->output()),
            ($line->payslip_number ?: ('payslip-'.$period->id.'-'.$line->id)).'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function payrollLinePayslipEmail(Request $request, AccountingPayrollPeriod $period, AccountingPayrollLine $line): RedirectResponse
    {
        $this->ensurePayrollScope($request, $period);
        abort_unless((int) $line->accounting_payroll_period_id === (int) $period->id, 404);
        $line->load('employee');
        $employee = $line->employee;
        if (! $employee || ! $employee->email) {
            return back()->withErrors(['email' => 'Employee email is missing.']);
        }

        SendPayrollPayslipEmailJob::dispatch((int) $period->id, (int) $line->id);

        return back()->with('success', 'Payslip email queued for delivery.');
    }

    public function payrollPayslipsEmailAll(Request $request, AccountingPayrollPeriod $period): RedirectResponse
    {
        $this->ensurePayrollScope($request, $period);
        $period->load(['lines.employee']);

        $queued = 0;
        $skipped = 0;
        foreach ($period->lines as $line) {
            $employee = $line->employee;
            if (! $employee || ! $employee->email) {
                $skipped++;
                continue;
            }
            SendPayrollPayslipEmailJob::dispatch((int) $period->id, (int) $line->id);
            $queued++;
        }

        return back()->with('success', 'Payslips queued. Queued: '.$queued.', Skipped (missing email): '.$skipped.'.');
    }

    public function payrollLinePaymentUpdate(Request $request, AccountingPayrollPeriod $period, AccountingPayrollLine $line): RedirectResponse
    {
        $this->ensurePayrollScope($request, $period);
        abort_unless((int) $line->accounting_payroll_period_id === (int) $period->id, 404);
        if (! in_array((string) $period->status, [AccountingPayrollPeriod::STATUS_POSTED, AccountingPayrollPeriod::STATUS_REVERSED], true)) {
            return back()->withErrors(['payment' => 'Only posted payroll runs can be reconciled with payments.']);
        }
        if ((string) $period->status === AccountingPayrollPeriod::STATUS_REVERSED) {
            return back()->withErrors(['payment' => 'Reversed payroll runs cannot be marked as paid.']);
        }

        $data = $request->validate([
            'payment_status' => ['required', 'in:paid,unpaid'],
            'payment_date' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $isPaid = (string) $data['payment_status'] === 'paid';
        $line->forceFill([
            'payment_status' => $data['payment_status'],
            'payment_date' => $isPaid ? ($data['payment_date'] ?? now()->toDateString()) : null,
            'payment_reference' => $isPaid ? ($data['payment_reference'] ?? null) : null,
        ])->save();

        return back()->with('success', 'Payslip payment status updated.');
    }

    public function payrollEmployeeStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accounting_payroll_period_id' => ['required', 'integer', 'exists:accounting_payroll_periods,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'basic_pay' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        $period = AccountingPayrollPeriod::query()->findOrFail((int) $data['accounting_payroll_period_id']);
        $this->ensurePayrollScope($request, $period);
        if (! in_array((string) $period->status, [AccountingPayrollPeriod::STATUS_DRAFT, AccountingPayrollPeriod::STATUS_APPROVED], true)) {
            return back()->withErrors(['period' => 'Posted/reversed runs are not editable.'])->withInput();
        }

        $allowances = (float) ($data['allowances'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        $basic = (float) $data['basic_pay'];
        $gross = $basic + $allowances;
        $net = $gross - $deductions;

        if ($net <= 0) {
            return back()->withErrors(['deductions' => 'Deductions cannot be equal to or exceed gross pay.'])->withInput();
        }

        $line = AccountingPayrollLine::query()->updateOrCreate(
            [
                'accounting_payroll_period_id' => $period->id,
                'employee_id' => (int) $data['employee_id'],
            ],
            [
                'basic_pay' => $basic,
                'allowances' => $allowances,
                'gross_pay' => $gross,
                'deductions' => $deductions,
                'net_pay' => $net,
                'payslip_number' => 'PSL-'.$period->period_year.str_pad((string) $period->period_month, 2, '0', STR_PAD_LEFT).'-'.$data['employee_id'],
            ]
        );
        $period->update([
            'total_gross' => (float) $period->lines()->sum('gross_pay'),
            'total_deductions' => (float) $period->lines()->sum('deductions'),
            'total_net' => (float) $period->lines()->sum('net_pay'),
        ]);

        if ($request->boolean('send_email')) {
            $employee = Employee::query()->find((int) $data['employee_id']);
            if ($employee && $employee->email) {
                Mail::raw(
                    'Payslip '.$line->payslip_number.' for '.$period->label.' | Net pay: '.PropertyMoney::kes((float) $line->net_pay),
                    fn ($mail) => $mail->to($employee->email)->subject('Payslip '.$period->label)
                );
                $line->forceFill(['email_sent_at' => now()])->save();
            }
        }

        return redirect()->route('property.accounting.payroll.show', ['period' => $period->id])->with('success', 'Payslip breakdown generated.');
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

        return property_view('property.agent.accounting.payroll.payslip', [
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

    public function chartOfAccounts(Request $request): View
    {
        $user = $request->user();
        $agentId = (int) ($user?->id ?? 0);
        $q = trim($request->string('q')->toString());
        $type = strtolower(trim($request->string('type')->toString()));
        $status = strtolower(trim($request->string('status')->toString()));
        $systemFilter = strtolower(trim($request->string('system_filter')->toString()));
        $usageFilter = strtolower(trim($request->string('usage')->toString()));

        $query = AccountingChartAccount::query()
            ->with('parent')
            ->where(function ($sq) use ($agentId) {
                $sq->whereNull('agent_user_id')->orWhere('agent_user_id', $agentId);
            })
            ->orderBy('code');

        if ($q !== '') {
            $query->where(function ($sq) use ($q) {
                $sq->where('code', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%');
            });
        }
        if (in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true)) {
            $query->where(function ($q) use ($type) {
                $q->where('type', $type)->orWhere('account_type', $type);
            });
        }
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'disabled' || $status === 'inactive') {
            $query->where('is_active', false);
        }

        $accounts = $query->get();
        $accountIds = $accounts->pluck('id')->all();

        $journalStats = AccountingJournalLine::query()
            ->where(function ($q) use ($accountIds) {
                $q->whereIn('account_id', $accountIds)->orWhereIn('accounting_chart_account_id', $accountIds);
            })
            ->selectRaw('COALESCE(account_id, accounting_chart_account_id) as aid')
            ->selectRaw('COUNT(*) as tx_count')
            ->selectRaw('COALESCE(SUM(debit),0) as debit_total')
            ->selectRaw('COALESCE(SUM(credit),0) as credit_total')
            ->groupBy('aid')
            ->get()
            ->keyBy('aid');

        $pmStats = PmAccountingEntry::query()
            ->selectRaw('account_name, COUNT(*) as tx_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END),0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END),0) as credit_total")
            ->groupBy('account_name')
            ->get()
            ->keyBy('account_name');

        $accountMap = PropertyAccountingPostingService::accountMap();
        $protectedCodes = ['1100', '1200', '1250', '2100', '2200', '2260', '2300', '2350', '4100', '4200', '5101'];
        $protectedNameHints = ['cash', 'accounts receivable', 'landlord payable', 'tenant deposit', 'suspense', 'accounts payable', 'payroll payable', 'rental income', 'management fee', 'maintenance expense'];

        $typed = $accounts->map(function (AccountingChartAccount $a) use ($journalStats, $pmStats, $accountMap, $protectedCodes, $protectedNameHints) {
            $type = strtolower((string) ($a->type ?: $a->account_type));
            $j = $journalStats->get((int) $a->id);
            $p = $pmStats->get((string) $a->name);
            $debit = (float) ($j->debit_total ?? 0) + (float) ($p->debit_total ?? 0);
            $credit = (float) ($j->credit_total ?? 0) + (float) ($p->credit_total ?? 0);
            $txCount = (int) ($j->tx_count ?? 0) + (int) ($p->tx_count ?? 0);

            $usage = $this->resolveAccountUsage((string) $a->name, (string) $a->code, $accountMap, $type, $txCount);
            $nameLc = strtolower((string) $a->name);
            $isProtected = in_array((string) $a->code, $protectedCodes, true)
                || collect($protectedNameHints)->contains(fn ($h) => str_contains($nameLc, $h))
                || (bool) ($a->is_control_account ?? false)
                || (bool) ($a->is_controlled_account ?? false);
            $isSystem = is_null($a->agent_user_id) || $isProtected;
            $normal = strtolower((string) ($a->normal_balance ?: (($type === 'asset' || $type === 'expense') ? 'debit' : 'credit')));
            $balance = $normal === 'debit' ? ($debit - $credit) : ($credit - $debit);

            return [
                'model' => $a,
                'id' => (int) $a->id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
                'type' => $type,
                'parent_id' => $a->parent_id ? (int) $a->parent_id : null,
                'parent_name' => (string) optional($a->parent)->name,
                'balance' => $balance,
                'tx_count' => $txCount,
                'usage' => $usage,
                'is_active' => (bool) $a->is_active,
                'is_system' => $isSystem,
                'is_control' => (bool) ($a->is_control_account ?? false) || (bool) ($a->is_controlled_account ?? false),
                'is_protected' => $isProtected,
                'mapping_used' => in_array((string) $a->name, array_values($accountMap), true),
            ];
        });

        if ($systemFilter === 'system') {
            $typed = $typed->where('is_system', true)->values();
        } elseif ($systemFilter === 'custom') {
            $typed = $typed->where('is_system', false)->values();
        }
        if ($usageFilter !== '' && $usageFilter !== 'all') {
            $typed = $typed->filter(function (array $a) use ($usageFilter) {
                return collect($a['usage'])->contains(fn ($u) => strtolower((string) $u) === $usageFilter);
            })->values();
        }

        $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
        $groups = collect($typeOrder)->map(function (string $groupType) use ($typed) {
            $items = $typed->where('type', $groupType)->values();
            if ($items->isEmpty()) {
                return null;
            }
            $indexed = $items->keyBy('id')->all();
            $rows = $this->flattenAccountHierarchy($indexed, null, 0);
            return [
                'type' => $groupType,
                'label' => ucfirst($groupType).'s',
                'total_balance' => (float) $items->sum('balance'),
                'count' => $items->count(),
                'rows' => $rows,
            ];
        })->filter()->values();

        $summarySource = $typed;
        $summary = [
            'total_accounts' => $summarySource->count(),
            'assets_balance' => (float) $summarySource->where('type', 'asset')->sum('balance'),
            'liabilities_balance' => (float) $summarySource->where('type', 'liability')->sum('balance'),
            'income_balance' => (float) $summarySource->where('type', 'income')->sum('balance'),
            'expenses_balance' => (float) $summarySource->where('type', 'expense')->sum('balance'),
            'disabled_accounts' => (int) $summarySource->where('is_active', false)->count(),
        ];

        return property_view('property.agent.accounting.chart_accounts', [
            'groups' => $groups,
            'summary' => $summary,
            'typeOptions' => ['asset', 'liability', 'equity', 'income', 'expense'],
            'usageOptions' => ['invoice', 'payment', 'maintenance', 'payroll', 'landlord', 'deposit', 'manual'],
            'filters' => [
                'q' => $q,
                'type' => $type,
                'status' => $status,
                'system_filter' => $systemFilter,
                'usage' => $usageFilter,
            ],
            'parentOptions' => $typed->map(fn ($a) => ['id' => $a['id'], 'label' => $a['code'].' - '.$a['name']])->values(),
        ]);
    }

    public function storeChartAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,income,expense'],
            'parent_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'is_control_account' => ['nullable', 'boolean'],
            'default_usage' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,disabled'],
        ]);

        $exists = AccountingChartAccount::query()
            ->where('code', $data['code'])
            ->where(function ($q) use ($request) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $request->user()->id);
            })
            ->exists();
        if ($exists) {
            return back()->withErrors(['code' => 'Account code already exists in this workspace.'])->withInput();
        }

        $account = AccountingChartAccount::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'account_type' => $data['type'],
            'parent_id' => $data['parent_id'] ?? null,
            'normal_balance' => $data['normal_balance'],
            'is_control_account' => $request->boolean('is_control_account'),
            'agent_user_id' => $request->user()->id,
            'is_active' => $data['status'] === 'active',
            'module' => 'property',
        ]);

        $usage = strtolower(trim((string) ($data['default_usage'] ?? '')));
        if ($usage !== '') {
            $raw = (string) PropertyPortalSetting::getValue('property_coa_usage_map_json', '{}');
            $map = json_decode($raw, true);
            $map = is_array($map) ? $map : [];
            $map[(string) $account->id] = $usage;
            PropertyPortalSetting::query()->updateOrCreate(
                ['key' => 'property_coa_usage_map_json'],
                ['value' => json_encode($map, JSON_UNESCAPED_UNICODE)]
            );
        }

        return back()->with('success', 'Account created successfully.');
    }

    public function disableChartAccount(Request $request, AccountingChartAccount $account): RedirectResponse
    {
        $this->ensureChartAccountScope($request, $account);
        $request->validate([
            'confirm' => ['nullable', 'in:yes'],
            'tx_count' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($this->isProtectedChartAccount($account)) {
            return back()->withErrors(['account' => 'This system/control account cannot be disabled.']);
        }

        $txCount = $this->chartAccountTransactionCount($account);
        if ($txCount > 0 && $request->input('confirm') !== 'yes') {
            return back()->withErrors(['account' => 'Account has transactions. Confirm disable first.']);
        }

        $account->is_active = false;
        $account->save();

        return back()->with('success', 'Account disabled.');
    }

    public function cloneChartAccount(Request $request, AccountingChartAccount $account): RedirectResponse
    {
        $this->ensureChartAccountScope($request, $account);
        $base = preg_replace('/[^0-9]/', '', (string) $account->code) ?: ((string) $account->code);
        $newCode = $base.'9';
        while (AccountingChartAccount::query()->where('code', $newCode)->exists()) {
            $newCode .= '9';
        }

        AccountingChartAccount::query()->create([
            'code' => $newCode,
            'name' => $account->name.' (Copy)',
            'type' => $account->type ?: $account->account_type,
            'account_type' => $account->type ?: $account->account_type,
            'parent_id' => $account->parent_id,
            'normal_balance' => $account->normal_balance,
            'is_control_account' => false,
            'agent_user_id' => $request->user()->id,
            'is_active' => true,
            'module' => 'property',
        ]);

        return back()->with('success', 'Account cloned.');
    }

    public function setDefaultUsage(Request $request, AccountingChartAccount $account): RedirectResponse
    {
        $this->ensureChartAccountScope($request, $account);
        $data = $request->validate(['usage' => ['required', 'in:invoice,payment,maintenance,payroll,landlord,deposit,manual']]);
        $raw = (string) PropertyPortalSetting::getValue('property_coa_usage_map_json', '{}');
        $map = json_decode($raw, true);
        $map = is_array($map) ? $map : [];
        $map[(string) $account->id] = $data['usage'];
        PropertyPortalSetting::query()->updateOrCreate(
            ['key' => 'property_coa_usage_map_json'],
            ['value' => json_encode($map, JSON_UNESCAPED_UNICODE)]
        );

        return back()->with('success', 'Default usage mapping updated.');
    }

    public function exportChartOfAccounts(Request $request): StreamedResponse
    {
        $dataRequest = Request::create('/internal', 'GET', $request->query());
        $dataRequest->setUserResolver(fn () => $request->user());
        $view = $this->chartOfAccounts($dataRequest);
        $payload = $view->getData();
        $groups = collect($payload['groups'] ?? []);
        $format = strtolower((string) $request->query('format', 'csv'));

        return TabularExport::stream(
            'property-chart-of-accounts',
            ['Type', 'Code', 'Account Name', 'Parent', 'Balance', 'Usage', 'Status', 'Protection', 'Transactions'],
            function () use ($groups) {
                foreach ($groups as $group) {
                    foreach (($group['rows'] ?? []) as $row) {
                        yield [
                            (string) ($group['label'] ?? ''),
                            (string) ($row['code'] ?? ''),
                            str_repeat('  ', (int) ($row['level'] ?? 0)).(string) ($row['name'] ?? ''),
                            (string) ($row['parent_name'] ?? ''),
                            (string) ($row['balance'] ?? 0),
                            implode('|', (array) ($row['usage'] ?? [])),
                            ! empty($row['is_active']) ? 'active' : 'disabled',
                            ! empty($row['is_control']) ? 'control' : (! empty($row['is_system']) ? 'system' : 'custom'),
                            (string) ($row['tx_count'] ?? 0),
                        ];
                    }
                }
            },
            $format
        );
    }

    public function journalBatches(Request $request): View
    {
        $agentUserId = (int) $request->user()->id;
        $status = strtolower(trim($request->string('status')->toString()));
        $sourceType = trim($request->string('source_type')->toString());
        $from = $request->input('from');
        $to = $request->input('to');
        $propertyId = (int) $request->integer('property_id');
        $createdBy = (int) $request->integer('created_by');

        $batchQuery = AccountingJournalBatch::query()
            ->with(['createdByUser:id,name'])
            ->where('agent_user_id', $agentUserId);

        if (in_array($status, [AccountingJournalBatch::STATUS_DRAFT, AccountingJournalBatch::STATUS_POSTED, AccountingJournalBatch::STATUS_REVERSED], true)) {
            $batchQuery->where('status', $status);
        }
        if ($sourceType !== '') {
            $batchQuery->where('source_type', $sourceType);
        }
        if ($request->filled('from')) {
            $batchQuery->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $batchQuery->whereDate('date', '<=', $request->date('to'));
        }
        if ($propertyId > 0) {
            $batchQuery->whereHas('lines', fn ($q) => $q->where('property_id', $propertyId));
        }
        if ($createdBy > 0) {
            $batchQuery->where('created_by', $createdBy);
        }

        $summaryQuery = clone $batchQuery;
        $summaryBatchIds = (clone $summaryQuery)->pluck('id')->all();
        $summaryTotals = ['debit' => 0.0, 'credit' => 0.0];
        if ($summaryBatchIds !== []) {
            $summaryTotals = (array) AccountingJournalLine::query()
                ->whereIn('batch_id', $summaryBatchIds)
                ->where('agent_user_id', $agentUserId)
                ->selectRaw('COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
                ->first()
                ?->toArray();
        }

        $summary = [
            'total_batches' => (int) (clone $summaryQuery)->count(),
            'total_debit' => (float) ($summaryTotals['debit'] ?? 0),
            'total_credit' => (float) ($summaryTotals['credit'] ?? 0),
            'posted_batches' => (int) (clone $summaryQuery)->where('status', AccountingJournalBatch::STATUS_POSTED)->count(),
            'reversed_batches' => (int) (clone $summaryQuery)->where('status', AccountingJournalBatch::STATUS_REVERSED)->count(),
        ];

        $batches = $batchQuery
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $batchIds = $batches->getCollection()->pluck('id')->all();
        $lineTotals = collect();
        $batchLines = collect();
        if ($batchIds !== []) {
            $lineTotals = AccountingJournalLine::query()
                ->whereIn('batch_id', $batchIds)
                ->where('agent_user_id', $agentUserId)
                ->selectRaw('batch_id, COALESCE(SUM(debit),0) as debit_total, COALESCE(SUM(credit),0) as credit_total')
                ->groupBy('batch_id')
                ->get()
                ->keyBy('batch_id');
            $batchLines = AccountingJournalLine::query()
                ->with(['structuredAccount:id,code,name'])
                ->whereIn('batch_id', $batchIds)
                ->where('agent_user_id', $agentUserId)
                ->orderBy('id')
                ->get()
                ->groupBy('batch_id');
        }

        $sourceTypes = AccountingJournalBatch::query()
            ->where('agent_user_id', $agentUserId)
            ->select('source_type')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type');

        $properties = Property::query()->orderBy('name')->get(['id', 'name']);
        $creators = DB::table('users')
            ->join('accounting_journal_batches', 'accounting_journal_batches.created_by', '=', 'users.id')
            ->where('accounting_journal_batches.agent_user_id', $agentUserId)
            ->selectRaw('users.id, users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get();
        $accounts = AccountingChartAccount::query()
            ->where(function ($q) use ($agentUserId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentUserId);
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $sourceLinks = [];
        foreach ($batches as $batch) {
            $sourceLinks[(int) $batch->id] = $this->resolveBatchSourceLink($batch);
        }

        return property_view('property.agent.accounting.journal_batches', [
            'batches' => $batches,
            'lineTotals' => $lineTotals,
            'batchLines' => $batchLines,
            'summary' => $summary,
            'sourceLinks' => $sourceLinks,
            'sourceTypes' => $sourceTypes,
            'properties' => $properties,
            'creators' => $creators,
            'accounts' => $accounts,
            'filters' => compact('status', 'sourceType', 'from', 'to', 'propertyId', 'createdBy'),
        ]);
    }

    public function exportJournalBatch(Request $request, AccountingJournalBatch $batch): StreamedResponse
    {
        $agentUserId = (int) $request->user()->id;
        abort_unless((int) $batch->agent_user_id === $agentUserId, 403);

        $batch->load(['createdByUser:id,name', 'lines.structuredAccount:id,code,name']);

        return TabularExport::stream(
            'journal-batch-'.$batch->id,
            ['Batch ID', 'Date', 'Source', 'Status', 'Created By', 'Account Code', 'Account Name', 'Debit', 'Credit', 'Memo', 'Reference'],
            function () use ($batch) {
                foreach ($batch->lines as $line) {
                    $account = $line->structuredAccount;
                    yield [
                        (string) $batch->id,
                        optional($batch->date)->format('Y-m-d') ?? '',
                        (string) $batch->source_type,
                        (string) $batch->status,
                        (string) ($batch->createdByUser?->name ?? 'System'),
                        (string) ($account?->code ?? ''),
                        (string) ($account?->name ?? 'Unknown account'),
                        (string) ((float) $line->debit),
                        (string) ((float) $line->credit),
                        (string) ($line->memo ?? ''),
                        (string) ($line->reference ?? ''),
                    ];
                }
            },
            'csv'
        );
    }

    public function accountsReceivable(Request $request): View
    {
        $propertyId = (int) $request->integer('property_id');
        $tenantId = (int) $request->integer('tenant_id');
        $overdue = strtolower(trim($request->string('overdue')->toString()));
        $minBalance = (float) $request->input('min_balance', 0);
        $maxBalanceRaw = trim((string) $request->input('max_balance', ''));
        $maxBalance = is_numeric($maxBalanceRaw) ? (float) $maxBalanceRaw : null;

        $rows = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->whereColumn('amount_paid', '<', 'amount')
            ->when($propertyId > 0, fn ($q) => $q->whereHas('unit.property', fn ($sq) => $sq->where('id', $propertyId)))
            ->when($tenantId > 0, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->when($overdue === 'overdue', fn ($q) => $q->where('due_date', '<', now()->toDateString()))
            ->when($overdue === 'current', fn ($q) => $q->where('due_date', '>=', now()->toDateString()))
            ->orderByDesc('due_date')
            ->paginate(50)
            ->withQueryString();

        if ($minBalance > 0 || $maxBalance !== null) {
            $rows->setCollection($rows->getCollection()->filter(function (PmInvoice $inv) use ($minBalance, $maxBalance) {
                $bal = max(0.0, (float) $inv->amount - (float) $inv->amount_paid);
                if ($bal < $minBalance) {
                    return false;
                }
                if ($maxBalance !== null && $bal > $maxBalance) {
                    return false;
                }
                return true;
            })->values());
        }

        return property_view('property.agent.accounting.receivables_accounts', [
            'rows' => $rows,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'tenants' => PmTenant::query()->orderBy('name')->get(['id', 'name']),
            'filters' => compact('propertyId', 'tenantId', 'overdue', 'minBalance', 'maxBalanceRaw'),
        ]);
    }

    public function tenantStatements(Request $request): View
    {
        $tenants = PmTenant::query()
            ->withCount('invoices')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return property_view('property.agent.accounting.receivables_tenant_statements', ['tenants' => $tenants]);
    }

    public function landlordPayables(Request $request): View
    {
        $propertyId = (int) $request->integer('property_id');
        $landlord = trim($request->string('landlord')->toString());
        $rows = PmLandlordLedgerEntry::query()
            ->selectRaw('user_id, property_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END),0) as amount_due")
            ->when($propertyId > 0, fn ($q) => $q->where('property_id', $propertyId))
            ->groupBy('user_id', 'property_id')
            ->havingRaw('amount_due > 0')
            ->with(['user', 'property'])
            ->when($landlord !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$landlord.'%')))
            ->orderByDesc('amount_due')
            ->paginate(50)
            ->withQueryString();

        return property_view('property.agent.accounting.payables_landlord', [
            'rows' => $rows,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['property_id' => $propertyId, 'landlord' => $landlord],
        ]);
    }

    public function landlordPayouts(Request $request): View
    {
        $status = strtolower(trim($request->string('status')->toString()));
        $rows = PmLandlordPayout::query()
            ->when(in_array($status, ['draft', 'approved', 'paid'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return property_view('property.agent.accounting.payables_landlord_payouts', [
            'rows' => $rows,
            'filters' => compact('status'),
        ]);
    }

    public function accountsPayable(Request $request): View
    {
        $status = strtolower(trim($request->string('status')->toString()));
        $supplier = trim($request->string('supplier')->toString());
        $agentId = AgentWorkspaceScope::shouldApply() ? (int) $request->user()?->id : null;
        $rows = Schema::hasTable('pm_supplier_invoices')
            ? DB::table('pm_supplier_invoices as i')
                ->leftJoin('pm_suppliers as s', 's.id', '=', 'i.supplier_id')
                ->selectRaw('i.id, s.name as supplier_name, i.invoice_no, i.amount, i.invoice_date as due_date, i.status')
                ->when($status !== '', fn ($q) => $q->where('i.status', $status))
                ->when($supplier !== '', fn ($q) => $q->where('s.name', 'like', '%'.$supplier.'%'))
                ->when($agentId !== null, fn ($q) => $q->where('i.agent_user_id', $agentId))
                ->orderByDesc('i.id')
                ->paginate(50)
            : new LengthAwarePaginator([], 0, 50, 1, ['path' => $request->url(), 'query' => $request->query()]);

        return property_view('property.agent.accounting.payables_accounts', [
            'rows' => $rows,
            'filters' => ['status' => $status, 'supplier' => $supplier],
        ]);
    }

    public function bankReconciliation(Request $request): View
    {
        $cashSide = PmAccountingEntry::query()
            ->where(function ($q) {
                $q->where('account_name', 'like', '%cash%')->orWhere('account_name', 'like', '%bank%');
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $bankSide = Schema::hasTable('unassigned_payments')
            ? UnassignedPayment::query()->orderByDesc('id')->limit(100)->get()
            : collect();

        return property_view('property.agent.accounting.cash_bank_reconciliation', [
            'cashSide' => $cashSide,
            'bankSide' => $bankSide,
        ]);
    }

    public function balanceSheet(Request $request): View
    {
        $asAt = $request->date('as_at')?->toDateString() ?? now()->toDateString();
        $entries = PmAccountingEntry::query()->whereDate('entry_date', '<=', $asAt)->get();
        $assets = (float) $entries->where('category', PmAccountingEntry::CATEGORY_ASSET)->reduce(fn ($c, $e) => $c + ($e->entry_type === PmAccountingEntry::TYPE_DEBIT ? (float) $e->amount : -(float) $e->amount), 0);
        $liabilities = (float) $entries->where('category', PmAccountingEntry::CATEGORY_LIABILITY)->reduce(fn ($c, $e) => $c + ($e->entry_type === PmAccountingEntry::TYPE_CREDIT ? (float) $e->amount : -(float) $e->amount), 0);
        $equity = (float) $entries->where('category', PmAccountingEntry::CATEGORY_EQUITY)->reduce(fn ($c, $e) => $c + ($e->entry_type === PmAccountingEntry::TYPE_CREDIT ? (float) $e->amount : -(float) $e->amount), 0);

        return property_view('property.agent.accounting.reports.balance_sheet', compact('asAt', 'assets', 'liabilities', 'equity'));
    }

    public function agedReceivables(Request $request): View
    {
        $rows = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->whereColumn('amount_paid', '<', 'amount')
            ->get()
            ->map(function (PmInvoice $inv) {
                $balance = max(0.0, (float) $inv->amount - (float) $inv->amount_paid);
                $days = $inv->due_date ? max(0, $inv->due_date->diffInDays(now(), false) * -1) : 0;
                return ['invoice' => $inv, 'balance' => $balance, 'days' => $days];
            });

        return property_view('property.agent.accounting.reports.aged_receivables', ['rows' => $rows]);
    }

    public function agedPayables(Request $request): View
    {
        $agentId = AgentWorkspaceScope::shouldApply() ? (int) $request->user()?->id : null;
        $rows = Schema::hasTable('pm_supplier_invoices')
            ? DB::table('pm_supplier_invoices as i')
                ->leftJoin('pm_suppliers as s', 's.id', '=', 'i.supplier_id')
                ->selectRaw('s.name as supplier_name, i.amount, i.invoice_date, i.status')
                ->when($agentId !== null, fn ($q) => $q->where('i.agent_user_id', $agentId))
                ->get()
            : collect();

        return property_view('property.agent.accounting.reports.aged_payables', ['rows' => $rows]);
    }

    public function depositLiabilityReport(Request $request): View
    {
        $agentId = AgentWorkspaceScope::shouldApply() ? (int) $request->user()?->id : null;
        $rows = Schema::hasTable('pm_tenant_deposits')
            ? DB::table('pm_tenant_deposits as d')
                ->leftJoin('pm_tenants as t', 't.id', '=', 'd.tenant_id')
                ->selectRaw('t.name as tenant_name, d.amount, d.status')
                ->when($agentId !== null, fn ($q) => $q->where('d.agent_user_id', $agentId))
                ->orderByDesc('d.id')
                ->get()
            : collect();

        return property_view('property.agent.accounting.reports.deposit_liability', ['rows' => $rows]);
    }

    public function reversals(Request $request): View
    {
        $entryReversals = PmAccountingEntry::query()
            ->whereNotNull('reversal_of_id')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return property_view('property.agent.accounting.controls.reversals', ['rows' => $entryReversals]);
    }

    public function periods(Request $request): View
    {
        $rows = AccountingPeriod::query()->orderByDesc('start_date')->paginate(50)->withQueryString();

        return property_view('property.agent.accounting.controls.periods', ['rows' => $rows]);
    }

    public function updatePeriodStatus(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed,locked']]);
        $period->status = $data['status'];
        if ($data['status'] !== 'open') {
            $period->closed_by = $request->user()->id;
            $period->closed_at = now();
        }
        $period->save();

        return back()->with('success', 'Period status updated.');
    }

    public function accountMapping(): View
    {
        return property_view('property.agent.accounting.settings.account_mapping', [
            'accountMap' => PropertyAccountingPostingService::accountMap(),
        ]);
    }

    public function financialSettings(): View
    {
        $payrollRaw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $payroll = is_string($payrollRaw) ? (json_decode($payrollRaw, true) ?: []) : [];
        $defaultCommission = PropertyPortalSetting::getValue('commission_default_percent', '10');

        return property_view('property.agent.accounting.settings.financial_settings', [
            'defaultCommission' => $defaultCommission,
            'payroll' => $payroll,
        ]);
    }

    private function postPayrollRun(Request $request, AccountingPayrollPeriod $period): RedirectResponse
    {
        if ($period->status !== AccountingPayrollPeriod::STATUS_APPROVED) {
            return back()->withErrors(['payroll' => 'Cannot post payroll unless it is approved.']);
        }
        if ($period->journal_batch_id) {
            return back()->withErrors(['payroll' => 'Payroll run already posted.']);
        }
        if (abs(((float) $period->total_gross) - (((float) $period->total_net) + ((float) $period->total_deductions))) > 0.001) {
            return back()->withErrors(['payroll' => 'Payroll totals are not balanced (gross must equal net + deductions).']);
        }

        $postingPeriod = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $period->period_end?->toDateString() ?? now()->toDateString())
            ->whereDate('end_date', '>=', $period->period_end?->toDateString() ?? now()->toDateString())
            ->first();
        if ($postingPeriod && in_array((string) $postingPeriod->status, ['closed', 'locked'], true)) {
            return back()->withErrors(['payroll' => 'Cannot post into a locked/closed accounting period.']);
        }

        $raw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $expenseAccountName = (string) ($settings['expense_account'] ?? 'Payroll Expense');
        $payableAccountName = (string) ($settings['payable_account'] ?? 'Payroll Payable');
        $deductionsPayableAccountName = (string) ($settings['deductions_payable_account'] ?? 'Payroll Deductions Payable');

        $expenseAccount = AccountingChartAccount::query()->where('name', $expenseAccountName)->first();
        $payableAccount = AccountingChartAccount::query()->where('name', $payableAccountName)->first();
        $deductionsAccount = AccountingChartAccount::query()->where('name', $deductionsPayableAccountName)->first();
        if (! $expenseAccount || ! $payableAccount || ! $deductionsAccount) {
            return back()->withErrors(['payroll' => 'Payroll posting accounts are not fully mapped in settings.']);
        }
        foreach ([$expenseAccount, $payableAccount, $deductionsAccount] as $account) {
            if (! ((bool) ($account->is_active ?? true))) {
                return back()->withErrors(['payroll' => 'Cannot post to disabled accounts.']);
            }
        }

        DB::transaction(function () use ($request, $period, $expenseAccount, $payableAccount, $deductionsAccount): void {
            $batch = AccountingJournalBatch::query()->create([
                'date' => $period->period_end?->toDateString() ?? now()->toDateString(),
                'description' => 'Payroll run #'.$period->id.' for '.$period->label,
                'source_type' => 'payroll',
                'source_id' => $period->id,
                'event_type' => 'payroll_posting',
                'source_key' => 'payroll_run',
                'status' => AccountingJournalBatch::STATUS_POSTED,
                'agent_user_id' => $request->user()->id,
                'created_by' => $request->user()->id,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ]);

            AccountingJournalLine::query()->create([
                'batch_id' => $batch->id,
                'date' => $period->period_end?->toDateString() ?? now()->toDateString(),
                'account_id' => $expenseAccount->id,
                'accounting_chart_account_id' => $expenseAccount->id,
                'description' => 'Payroll expense for '.$period->label,
                'debit' => (float) $period->total_gross,
                'credit' => 0,
                'reference' => 'PAY-'.$period->id,
            ]);
            AccountingJournalLine::query()->create([
                'batch_id' => $batch->id,
                'date' => $period->period_end?->toDateString() ?? now()->toDateString(),
                'account_id' => $payableAccount->id,
                'accounting_chart_account_id' => $payableAccount->id,
                'description' => 'Payroll payable for '.$period->label,
                'debit' => 0,
                'credit' => (float) $period->total_net,
                'reference' => 'PAY-'.$period->id,
            ]);
            if ((float) $period->total_deductions > 0) {
                AccountingJournalLine::query()->create([
                    'batch_id' => $batch->id,
                    'date' => $period->period_end?->toDateString() ?? now()->toDateString(),
                    'account_id' => $deductionsAccount->id,
                    'accounting_chart_account_id' => $deductionsAccount->id,
                    'description' => 'Statutory payroll deductions for '.$period->label,
                    'debit' => 0,
                    'credit' => (float) $period->total_deductions,
                    'reference' => 'PAY-'.$period->id,
                ]);
            }

            $this->mirrorPayrollToPmEntries(
                $request,
                (float) $period->total_gross,
                (float) $period->total_deductions,
                (float) $period->total_net,
                $period->period_end?->toDateString() ?? now()->toDateString(),
                'PAY-'.$period->id,
                false
            );

            $period->forceFill([
                'status' => AccountingPayrollPeriod::STATUS_POSTED,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
                'journal_batch_id' => $batch->id,
            ])->save();
        });

        return redirect()->route('property.accounting.payroll.show', ['period' => $period->id])->with('success', 'Payroll run posted to accounting.');
    }

    private function mirrorPayrollToPmEntries(Request $request, float $gross, float $deductions, float $net, string $entryDate, string $reference, bool $isReversal): void
    {
        $raw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $expenseAccount = (string) ($settings['expense_account'] ?? 'Payroll Expense');
        $payableAccount = (string) ($settings['payable_account'] ?? 'Payroll Payable');
        $deductionsPayableAccount = (string) ($settings['deductions_payable_account'] ?? 'Payroll Deductions Payable');

        $debitType = $isReversal ? PmAccountingEntry::TYPE_CREDIT : PmAccountingEntry::TYPE_DEBIT;
        $creditType = $isReversal ? PmAccountingEntry::TYPE_DEBIT : PmAccountingEntry::TYPE_CREDIT;
        $common = [
            'property_id' => null,
            'recorded_by_user_id' => $request->user()->id,
            'entry_date' => $entryDate,
            'reference' => $reference,
            'source_key' => $isReversal ? 'payroll_run_reversal' : 'payroll_run',
            'description' => $isReversal ? 'Payroll run reversal' : 'Payroll run posting',
        ];
        PmAccountingEntry::query()->create([
            ...$common,
            'account_name' => $expenseAccount,
            'category' => PmAccountingEntry::CATEGORY_EXPENSE,
            'entry_type' => $debitType,
            'amount' => $gross,
        ]);
        PmAccountingEntry::query()->create([
            ...$common,
            'account_name' => $payableAccount,
            'category' => PmAccountingEntry::CATEGORY_LIABILITY,
            'entry_type' => $creditType,
            'amount' => $net,
        ]);
        if ($deductions > 0) {
            PmAccountingEntry::query()->create([
                ...$common,
                'account_name' => $deductionsPayableAccount,
                'category' => PmAccountingEntry::CATEGORY_LIABILITY,
                'entry_type' => $creditType,
                'amount' => $deductions,
            ]);
        }
    }

    private function ensurePayrollScope(Request $request, AccountingPayrollPeriod $period): void
    {
        $agentUserId = (int) ($request->user()->id ?? 0);
        $ok = is_null($period->agent_user_id) || (int) $period->agent_user_id === $agentUserId;
        abort_unless($ok, 403);
    }

    /**
     * @return array{0:string,1:?string,2:\Illuminate\Support\Collection<int,object>}
     */
    private function buildRunPayslipPayload(AccountingPayrollPeriod $period, AccountingPayrollLine $line): array
    {
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
        $raw = PropertyPortalSetting::query()->where('key', 'property_payroll_settings')->value('value');
        $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $expenseAccount = (string) ($settings['expense_account'] ?? 'Payroll Expense');
        $payableAccount = (string) ($settings['payable_account'] ?? 'Payroll Payable');
        $deductionsAccount = (string) ($settings['deductions_payable_account'] ?? 'Payroll Deductions Payable');

        $entries = collect([
            (object) ['account_name' => $expenseAccount, 'entry_type' => 'debit', 'amount' => (float) $line->gross_pay],
            (object) ['account_name' => $payableAccount, 'entry_type' => 'credit', 'amount' => (float) $line->net_pay],
        ]);
        if ((float) $line->deductions > 0) {
            $entries->push((object) ['account_name' => $deductionsAccount, 'entry_type' => 'credit', 'amount' => (float) $line->deductions]);
        }

        return [$companyName, $logoUrl, $entries];
    }

    /**
     * @param  array<int,array<string,mixed>>  $indexed
     * @return array<int,array<string,mixed>>
     */
    private function flattenAccountHierarchy(array $indexed, ?int $parentId, int $level): array
    {
        $rows = [];
        $children = collect($indexed)
            ->filter(fn (array $a) => (($a['parent_id'] ?? null) === $parentId))
            ->sortBy('code')
            ->values()
            ->all();

        foreach ($children as $child) {
            $child['level'] = $level;
            $rows[] = $child;
            $rows = array_merge($rows, $this->flattenAccountHierarchy($indexed, (int) $child['id'], $level + 1));
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function resolveAccountUsage(string $accountName, string $code, array $accountMap, string $type, int $txCount): array
    {
        $tags = [];
        $nameLc = strtolower($accountName);
        $map = array_change_key_case($accountMap, CASE_LOWER);
        foreach ($map as $slot => $mappedName) {
            if (strtolower((string) $mappedName) !== $nameLc) {
                continue;
            }
            if (str_contains($slot, 'invoice') || str_contains($slot, 'receivable') || $slot === 'rental_income') {
                $tags[] = 'invoice';
            }
            if (str_contains($slot, 'cash') || str_contains($slot, 'payment')) {
                $tags[] = 'payment';
            }
            if (str_contains($slot, 'maintenance')) {
                $tags[] = 'maintenance';
            }
            if (str_contains($slot, 'payable')) {
                $tags[] = 'landlord';
            }
        }
        if (str_contains($nameLc, 'payroll') || str_starts_with($code, '23')) {
            $tags[] = 'payroll';
        }
        if (str_contains($nameLc, 'deposit')) {
            $tags[] = 'deposit';
        }
        if ($txCount > 0 && $tags === []) {
            $tags[] = 'manual';
        }
        if ($tags === [] && in_array($type, ['asset', 'liability', 'income', 'expense'], true)) {
            $tags[] = 'manual';
        }

        return array_values(array_unique($tags));
    }

    private function isProtectedChartAccount(AccountingChartAccount $account): bool
    {
        $code = (string) $account->code;
        $nameLc = strtolower((string) $account->name);
        $protectedCodes = ['1100', '1200', '1250', '2100', '2200', '2260', '2300', '2350', '4100', '4200', '5101'];
        $protectedNameHints = ['cash', 'accounts receivable', 'landlord payable', 'tenant deposit', 'suspense', 'accounts payable', 'payroll payable', 'rental income', 'management fee', 'maintenance expense'];
        if (in_array($code, $protectedCodes, true)) {
            return true;
        }
        if ((bool) ($account->is_control_account ?? false) || (bool) ($account->is_controlled_account ?? false)) {
            return true;
        }
        return collect($protectedNameHints)->contains(fn ($h) => str_contains($nameLc, $h));
    }

    private function chartAccountTransactionCount(AccountingChartAccount $account): int
    {
        $journalCount = (int) AccountingJournalLine::query()
            ->where('account_id', $account->id)
            ->orWhere('accounting_chart_account_id', $account->id)
            ->count();
        $pmCount = (int) PmAccountingEntry::query()->where('account_name', $account->name)->count();

        return $journalCount + $pmCount;
    }

    private function ensureChartAccountScope(Request $request, AccountingChartAccount $account): void
    {
        $agentId = (int) ($request->user()->id ?? 0);
        $ok = is_null($account->agent_user_id) || (int) $account->agent_user_id === $agentId;
        abort_unless($ok, 403);
    }

    private function journalSourceLabel(string $sourceType): string
    {
        return match (strtolower(trim($sourceType))) {
            'manual' => 'Manual',
            'pm_invoice' => 'Invoice',
            'pm_payment' => 'Payment',
            'pm_maintenance_job' => 'Maintenance',
            'payroll', 'payroll_batch', 'payroll_employee' => 'Payroll',
            default => ucfirst(str_replace('_', ' ', $sourceType)),
        };
    }

    /**
     * @return array{label:string,url:?string}
     */
    private function resolveBatchSourceLink(AccountingJournalBatch $batch): array
    {
        $sourceType = strtolower(trim((string) $batch->source_type));

        return match ($sourceType) {
            'pm_invoice' => [
                'label' => 'Invoice #'.$batch->source_id,
                'url' => ((int) $batch->source_id > 0 && Route::has('property.revenue.invoices.show'))
                    ? route('property.revenue.invoices.show', ['invoice' => (int) $batch->source_id])
                    : null,
            ],
            'pm_payment' => [
                'label' => 'Payment #'.$batch->source_id,
                'url' => Route::has('property.revenue.payments')
                    ? route('property.revenue.payments', ['payment_id' => (int) $batch->source_id])
                    : null,
            ],
            'pm_maintenance_job' => [
                'label' => 'Maintenance job #'.$batch->source_id,
                'url' => Route::has('property.maintenance.jobs')
                    ? route('property.maintenance.jobs', ['job_id' => (int) $batch->source_id])
                    : null,
            ],
            'payroll', 'payroll_batch', 'payroll_employee' => [
                'label' => 'Payroll',
                'url' => Route::has('property.accounting.payroll')
                    ? route('property.accounting.payroll', ['source_id' => (int) $batch->source_id])
                    : null,
            ],
            'manual' => [
                'label' => 'Manual by '.($batch->createdByUser?->name ?? 'user'),
                'url' => null,
            ],
            default => [
                'label' => $this->journalSourceLabel((string) $batch->source_type),
                'url' => null,
            ],
        };
    }

    private function buildAuditTrailBatchQuery(Request $request)
    {
        $agentId = (int) ($request->user()->id ?? 0);
        $query = AccountingJournalBatch::query()
            ->with(['createdByUser', 'postedByUser'])
            ->where(function ($q) use ($agentId) {
                $q->whereNull('agent_user_id')->orWhere('agent_user_id', $agentId);
            })
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }
        if ($request->filled('user_id')) {
            $uid = (int) $request->integer('user_id');
            $query->where(function ($q) use ($uid) {
                $q->where('created_by', $uid)->orWhere('posted_by', $uid);
            });
        }
        $actionType = trim((string) $request->input('action_type', ''));
        if ($actionType !== '') {
            $query->where('event_type', $actionType);
        }
        $entityType = trim((string) $request->input('entity_type', ''));
        if ($entityType !== '') {
            $query->where('source_type', $entityType);
        }
        $reference = trim((string) $request->input('reference', ''));
        if ($reference !== '') {
            $query->where(function ($q) use ($reference) {
                $q->where('source_key', 'like', '%'.$reference.'%')
                    ->orWhere('source_id', $reference);
            });
        }

        $sourceType = strtolower(trim((string) $request->input('source_type', '')));
        if ($sourceType !== '') {
            if ($sourceType === 'manual') {
                $query->where(function ($q) {
                    $q->where('source_type', 'like', '%manual%')->orWhere('event_type', 'like', '%manual%');
                });
            } elseif ($sourceType === 'api') {
                $query->where('source_type', 'like', '%api%');
            } elseif ($sourceType === 'webhook') {
                $query->where('source_type', 'like', '%webhook%');
            } elseif ($sourceType === 'system') {
                $query->where(function ($q) {
                    $q->where('source_type', 'not like', '%api%')
                        ->where('source_type', 'not like', '%webhook%')
                        ->where('source_type', 'not like', '%manual%');
                });
            }
        }

        if ($request->filled('property_id')) {
            $pid = (int) $request->integer('property_id');
            $query->whereHas('lines', fn ($q) => $q->where('property_id', $pid));
        }
        if ($request->filled('tenant_id')) {
            $tid = (int) $request->integer('tenant_id');
            $query->whereHas('lines', fn ($q) => $q->where('tenant_id', $tid));
        }
        if ($request->filled('account_id')) {
            $aid = (int) $request->integer('account_id');
            $query->whereHas('lines', function ($q) use ($aid) {
                $q->where('account_id', $aid)->orWhere('accounting_chart_account_id', $aid);
            });
        }
        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('description', 'like', '%'.$q.'%')
                    ->orWhere('source_key', 'like', '%'.$q.'%')
                    ->orWhere('source_type', 'like', '%'.$q.'%')
                    ->orWhere('event_type', 'like', '%'.$q.'%');
            });
        }

        return $query;
    }

    private function loadBatchLineSummaries(array $batchIds)
    {
        if ($batchIds === []) {
            return collect();
        }
        $lines = AccountingJournalLine::query()
            ->with('structuredAccount')
            ->whereIn('batch_id', $batchIds)
            ->get();

        return $lines->groupBy('batch_id')->map(function ($group) {
            $parts = $group->map(function (AccountingJournalLine $line) {
                $name = (string) ($line->structuredAccount?->name ?: 'Account');
                $delta = (float) $line->debit - (float) $line->credit;
                $prefix = $delta >= 0 ? '+' : '-';
                return $name.' '.$prefix.PropertyMoney::kes(abs($delta));
            })->take(3)->values()->all();
            $stacked = $group->map(function (AccountingJournalLine $line) {
                $name = (string) ($line->structuredAccount?->name ?: 'Account');
                if ((float) $line->debit > 0) {
                    return '<div class="text-emerald-700 dark:text-emerald-300">'.$name.' +'.PropertyMoney::kes((float) $line->debit).'</div>';
                }
                if ((float) $line->credit > 0) {
                    return '<div class="text-rose-700 dark:text-rose-300">'.$name.' -'.PropertyMoney::kes((float) $line->credit).'</div>';
                }

                return '<div>'.$name.' '.PropertyMoney::kes(0).'</div>';
            })->take(4)->values()->all();
            if ($group->count() > 3) {
                $parts[] = '...';
            }
            if ($group->count() > 4) {
                $stacked[] = '<div class="text-slate-500">...</div>';
            }

            return [
                'impact' => implode(' | ', $parts),
                'impact_html' => implode('', $stacked),
            ];
        });
    }

    private function auditSourceTypeLabel(AccountingJournalBatch $batch): string
    {
        $source = strtolower((string) $batch->source_type);
        if (str_contains($source, 'webhook')) {
            return 'Webhook';
        }
        if (str_contains($source, 'api')) {
            return 'API';
        }
        if (str_contains($source, 'manual')) {
            return 'Manual';
        }

        return 'System';
    }

    private function ensureAuditBatchScope(Request $request, AccountingJournalBatch $batch): void
    {
        $agentId = (int) ($request->user()->id ?? 0);
        $ok = is_null($batch->agent_user_id) || (int) $batch->agent_user_id === $agentId;
        abort_unless($ok, 403);
    }

    /**
     * @return array{type:string,record:mixed}
     */
    private function resolveAuditSourceRecord(AccountingJournalBatch $batch): array
    {
        return match ((string) $batch->source_type) {
            'pm_invoice' => ['type' => 'Invoice', 'record' => PmInvoice::query()->find($batch->source_id)],
            'pm_payment' => ['type' => 'Payment', 'record' => PmPayment::query()->find($batch->source_id)],
            'pm_maintenance_job' => ['type' => 'Maintenance', 'record' => PmMaintenanceJob::query()->find($batch->source_id)],
            'pm_landlord_payout' => ['type' => 'Payout', 'record' => PmLandlordPayout::query()->find($batch->source_id)],
            default => ['type' => ucfirst((string) $batch->source_type), 'record' => null],
        };
    }

    private function categoryFromAccountName(string $accountName): string
    {
        $account = AccountingChartAccount::query()
            ->where('name', $accountName)
            ->first();
        $category = strtolower((string) ($account?->type ?: $account?->account_type));
        if (in_array($category, array_keys(PmAccountingEntry::categoryOptions()), true)) {
            return $category;
        }

        return PmAccountingEntry::CATEGORY_EXPENSE;
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

