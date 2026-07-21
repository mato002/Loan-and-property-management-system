<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMessageLog;
use App\Models\PmPayment;
use App\Models\PmPenaltyRule;
use App\Models\PmTenant;
use App\Models\PmTenantNotice;
use App\Services\BulkSmsService;
use App\Services\Property\FinanceBalanceSnapshotService;
use App\Services\Property\PenaltyEngineService;
use App\Services\Property\FinancialReportingFormulaService;
use App\Services\Property\PropertyAgentContactResolver;
use App\Services\Property\PropertyCommunicationTemplateService;
use App\Services\Property\PropertyDashboardStats;
use App\Services\Property\PropertyMoney;
use App\Services\Property\RentInvoiceGenerator;
use App\Services\Property\RentRollQuery;
use App\Services\Property\TenantCommunicationStageService;
use App\Models\PropertyPortalSetting;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueController extends Controller
{
    public function collectionsOverview(): View
    {
        $stats = [
            [
                'label' => 'Billed (MTD)',
                'value' => PropertyMoney::kes(PropertyDashboardStats::mtdBilled()),
                'hint' => 'Issued billable invoices',
            ],
            [
                'label' => 'Collections (MTD)',
                'value' => PropertyMoney::kes(PropertyDashboardStats::mtdCollected()),
                'hint' => 'Completed payment allocations',
            ],
            [
                'label' => 'Tenant arrears',
                'value' => PropertyMoney::kes(PropertyDashboardStats::outstandingBalance()),
                'hint' => 'Open billable balances',
            ],
            [
                'label' => 'Collection rate',
                'value' => $this->formatCollectionRateLabel(PropertyDashboardStats::collectionRateMtd()),
                'hint' => 'MTD collected vs billed',
            ],
        ];

        return property_view('property.agent.revenue.collections_overview', [
            'stats' => $stats,
        ]);
    }

    /**
     * @param  array{target: float, actual: float|null, gap_kes: float}  $rate
     */
    private function formatCollectionRateLabel(array $rate): string
    {
        $actual = $rate['actual'];
        if ($actual === null) {
            return '—';
        }

        return number_format($actual, 1).'%';
    }

    public function rentRoll(Request $request): View|StreamedResponse
    {
        $rows = RentRollQuery::tableRows();
        $q = trim((string) $request->query('q', ''));
        $sort = strtolower(trim((string) $request->query('sort', 'unit')));
        $dir = strtolower(trim((string) $request->query('dir', 'asc')));
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $text = mb_strtolower(implode(' ', array_map(static fn ($c) => (string) $c, $row)));

                return str_contains($text, $needle);
            }));
        }
        $sortMap = ['unit' => 0, 'tenant' => 1, 'period' => 2, 'due' => 3, 'paid' => 5, 'balance' => 6, 'status' => 7];
        $sortIndex = $sortMap[$sort] ?? 0;
        usort($rows, static function (array $a, array $b) use ($sortIndex, $dir): int {
            $va = (string) ($a[$sortIndex] ?? '');
            $vb = (string) ($b[$sortIndex] ?? '');
            if (in_array($sortIndex, [3, 5, 6], true)) {
                $na = (float) preg_replace('/[^0-9.\-]/', '', $va);
                $nb = (float) preg_replace('/[^0-9.\-]/', '', $vb);

                return $dir === 'desc' ? ($nb <=> $na) : ($na <=> $nb);
            }

            return $dir === 'desc' ? strcasecmp($vb, $va) : strcasecmp($va, $vb);
        });

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return TabularExport::stream(
                'rent-roll-'.now()->format('Ymd_His'),
                ['Unit', 'Tenant', 'Period', 'Rent due', 'Other charges', 'Paid', 'Balance', 'Status'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield $row;
                    }
                },
                $export
            );
        }
        $paginator = $this->paginateRows($rows, $perPage, $request);
        $pageRows = $paginator->getCollection()->all();

        $stats = [
            ['label' => 'Billed (MTD)', 'value' => PropertyMoney::kes(app(FinancialReportingFormulaService::class)->billedMtd()), 'hint' => 'Billable issued'],
            ['label' => 'Collections (MTD)', 'value' => PropertyMoney::kes(PropertyDashboardStats::mtdCollected()), 'hint' => 'Completed allocation sums'],
            ['label' => 'Tenant arrears', 'value' => PropertyMoney::kes(PropertyDashboardStats::outstandingBalance()), 'hint' => 'Billable open balances'],
            ['label' => 'Units on roll', 'value' => (string) count($rows), 'hint' => 'Filtered total'],
        ];

        return property_view('property.agent.revenue.rent_roll', [
            'stats' => $stats,
            'columns' => ['Unit', 'Tenant', 'Period', 'Rent due', 'Other charges', 'Paid', 'Balance', 'Status'],
            'tableRows' => $pageRows,
            'paginator' => $paginator,
            'filters' => [
                'q' => $q,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function uninvoicedLeases(Request $request): View|StreamedResponse
    {
        $generator = app(RentInvoiceGenerator::class);
        $month = trim((string) $request->query('month', now()->format('Y-m')));
        $filter = strtolower(trim((string) $request->query('filter', 'missing')));
        if (! in_array($filter, ['missing', 'all', 'blocked', 'invoiced'], true)) {
            $filter = 'missing';
        }
        $q = trim((string) $request->query('q', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $allRows = $generator->reportRows($month);
        $counts = [
            'missing' => 0,
            'already' => 0,
            'no_unit' => 0,
            'zero_rent' => 0,
        ];
        foreach ($allRows as $row) {
            match ($row['reason']) {
                RentInvoiceGenerator::REASON_MISSING => $counts['missing']++,
                RentInvoiceGenerator::REASON_ALREADY => $counts['already']++,
                RentInvoiceGenerator::REASON_NO_UNIT => $counts['no_unit']++,
                RentInvoiceGenerator::REASON_ZERO_RENT => $counts['zero_rent']++,
                default => null,
            };
        }

        $rows = collect($allRows)->filter(function (array $row) use ($filter) {
            return match ($filter) {
                'missing' => $row['reason'] === RentInvoiceGenerator::REASON_MISSING,
                'blocked' => in_array($row['reason'], [
                    RentInvoiceGenerator::REASON_NO_UNIT,
                    RentInvoiceGenerator::REASON_ZERO_RENT,
                ], true),
                'invoiced' => $row['reason'] === RentInvoiceGenerator::REASON_ALREADY,
                default => true,
            };
        });

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(function (array $row) use ($needle) {
                $hay = mb_strtolower(implode(' ', [
                    $row['tenant_name'],
                    $row['property_name'],
                    $row['unit_label'],
                    $row['reason_label'],
                ]));

                return str_contains($hay, $needle);
            });
        }

        $rows = $rows->values();
        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return TabularExport::stream(
                'uninvoiced-leases-'.$month.'-'.now()->format('Ymd_His'),
                ['Tenant', 'Property', 'Unit', 'Bill amount', 'Due date', 'Status', 'Lease'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield [
                            $row['tenant_name'],
                            $row['property_name'],
                            $row['unit_label'],
                            number_format((float) $row['bill_amount'], 2, '.', ''),
                            $row['due_date'],
                            $row['reason_label'],
                            '#'.$row['lease_id'],
                        ];
                    }
                },
                $export
            );
        }

        $paginator = $this->paginateRows($rows->all(), $perPage, $request);
        $canGenerate = (bool) auth()->user()?->hasPmPermission('invoices.manage');

        $tableRows = $paginator->getCollection()->map(function (array $row) use ($month, $canGenerate) {
            $tenantCell = new HtmlString(
                '<a href="'.route('property.leases.edit', $row['lease_id'], false).'" data-turbo-frame="property-main" class="font-semibold text-indigo-700 hover:underline">'
                .e($row['tenant_name']).'</a>'
            );

            $reasonClass = match ($row['reason']) {
                RentInvoiceGenerator::REASON_MISSING => 'bg-amber-100 text-amber-800',
                RentInvoiceGenerator::REASON_ALREADY => 'bg-emerald-100 text-emerald-800',
                RentInvoiceGenerator::REASON_NO_UNIT, RentInvoiceGenerator::REASON_ZERO_RENT => 'bg-slate-100 text-slate-700',
                default => 'bg-slate-100 text-slate-700',
            };
            $statusCell = new HtmlString(
                '<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold '.$reasonClass.'">'.e($row['reason_label']).'</span>'
            );

            $actionCell = '—';
            if ($row['can_generate'] && $canGenerate) {
                $actionCell = new HtmlString(
                    '<form method="post" action="'.route('property.revenue.uninvoiced_leases.generate', absolute: false).'" data-turbo-frame="property-main" class="inline">'
                    .csrf_field()
                    .'<input type="hidden" name="month" value="'.e($month).'" />'
                    .'<input type="hidden" name="keys[]" value="'.e($row['key']).'" />'
                    .'<button type="submit" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Generate</button>'
                    .'</form>'
                );
            } elseif ($row['reason'] === RentInvoiceGenerator::REASON_NO_UNIT) {
                $actionCell = new HtmlString(
                    '<a href="'.route('property.leases.edit', $row['lease_id'], false).'" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-700 hover:underline">Fix lease</a>'
                );
            }

            $selectCell = $row['can_generate']
                ? new HtmlString(
                    '<input type="checkbox" name="keys[]" value="'.e($row['key']).'" form="uninvoiced-selected-form" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />'
                )
                : '';

            return [
                $selectCell,
                $tenantCell,
                e($row['property_name']),
                e($row['unit_label']),
                PropertyMoney::kes((float) $row['bill_amount']),
                $row['due_date'],
                $statusCell,
                $actionCell,
            ];
        })->all();

        $paginator->setCollection(collect($tableRows));

        $automationOn = PropertyPortalSetting::isRentInvoiceAutomationEnabled();

        return property_view('property.agent.revenue.uninvoiced_leases', [
            'stats' => [
                ['label' => 'Not invoiced', 'value' => (string) $counts['missing'], 'hint' => $month],
                ['label' => 'Already invoiced', 'value' => (string) $counts['already'], 'hint' => 'This month'],
                ['label' => 'No unit', 'value' => (string) $counts['no_unit'], 'hint' => 'Fix lease'],
                ['label' => 'Zero rent', 'value' => (string) $counts['zero_rent'], 'hint' => 'Cannot bill'],
            ],
            'columns' => ['', 'Tenant', 'Property', 'Unit', 'Bill amount', 'Due date', 'Status', 'Action'],
            'tableRows' => $tableRows,
            'paginator' => $paginator,
            'month' => $month,
            'automationOn' => $automationOn,
            'canGenerate' => $canGenerate,
            'filters' => [
                'month' => $month,
                'filter' => $filter,
                'q' => $q,
                'per_page' => (string) $perPage,
            ],
            'missingCount' => $counts['missing'],
        ]);
    }

    public function generateUninvoicedInvoices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string', 'max:64'],
            'generate_all' => ['nullable', 'boolean'],
        ]);

        $month = $data['month'];
        $generateAll = $request->boolean('generate_all');
        $keys = $generateAll
            ? null
            : collect($data['keys'] ?? [])->filter()->values()->all();

        if (! $generateAll && $keys === []) {
            return back()->withErrors(['keys' => 'Select at least one row, or use Generate all missing.'])->withInput();
        }

        $result = app(RentInvoiceGenerator::class)->generateMissing(
            $month,
            $keys,
            $request->user(),
        );

        $message = "Generated {$result['created']} rent invoice(s) for {$month}.";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }
        if ($result['errors'] !== []) {
            return back()
                ->with('warning', $message)
                ->with('bulk_invoice_errors', array_slice($result['errors'], 0, 8));
        }

        return redirect()
            ->route('property.revenue.uninvoiced_leases', ['month' => $month, 'filter' => 'missing'])
            ->with('success', $message);
    }

    public function invoicesBulk(Request $request): RedirectResponse
    {
        $action = strtolower((string) $request->input('action', ''));
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one invoice.']);
        }
        if ($action === 'cancel') {
            $cancelled = 0;
            $skipped = 0;
            $invoices = PmInvoice::query()
                ->whereIn('id', $ids)
                ->whereNotIn('status', [PmInvoice::STATUS_PAID, PmInvoice::STATUS_CANCELLED])
                ->where('amount_paid', 0)
                ->get();
            foreach ($invoices as $invoice) {
                $invoice->update([
                    'status' => PmInvoice::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $request->user()?->id,
                    'cancelled_reason' => 'Bulk cancel',
                ]);
                \App\Services\Property\PropertyAccountingPostingService::reverseInvoiceIssued($invoice, $request->user(), 'Bulk cancel');
                \App\Models\PmInvoiceEvent::record((int) $invoice->id, \App\Models\PmInvoiceEvent::EVENT_CANCELLED, $request->user()?->id, 'Bulk cancel');
                $cancelled++;
            }
            $skipped = count($ids) - $cancelled;

            return back()->with('status', "Cancelled {$cancelled} invoice(s)" . ($skipped > 0 ? ", skipped {$skipped} (paid or already cancelled)." : '.'));
        }

        if ($action === 'mark_sent') {
            $count = 0;
            $invoices = PmInvoice::query()
                ->whereIn('id', $ids)
                ->where('status', PmInvoice::STATUS_DRAFT)
                ->get();
            foreach ($invoices as $invoice) {
                $invoice->update([
                    'status' => PmInvoice::STATUS_SENT,
                    'sent_at' => now(),
                    'sent_by_user_id' => $request->user()?->id,
                ]);
                \App\Services\Property\PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());
                \App\Models\PmInvoiceEvent::record((int) $invoice->id, \App\Models\PmInvoiceEvent::EVENT_SENT, $request->user()?->id);
                $count++;
            }
            return back()->with('status', "Marked {$count} invoice(s) as sent.");
        }

        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function paymentsBulk(Request $request): RedirectResponse
    {
        $action = strtolower((string) $request->input('action', ''));
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one payment.']);
        }
        // For now only allow deleting pending/failed; completed records are ledgered
        if ($action === 'delete') {
            PmPayment::query()
                ->whereIn('id', $ids)
                ->whereIn('status', ['pending', 'failed'])
                ->delete();

            return back()->with('status', 'Selected pending/failed payments removed.');
        }

        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    /**
     * Tenant-rolled-up arrears index. One row per tenant; drill-down to
     * the per-invoice view via `arrearsTenant()`.
     */
    public function arrears(Request $request): View|StreamedResponse
    {
        [$rangeMonths, $rangeEndYm, $rangeFrom, $rangeTo, $dueRangeLabel] = $this->resolveArrearsDueRange($request);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'aging' => strtolower(trim((string) $request->query('aging', ''))),
            'workflow' => strtolower(trim((string) $request->query('workflow', ''))),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'range_months' => (string) $rangeMonths,
            'range_end' => $rangeEndYm,
            'sort' => strtolower(trim((string) $request->query('sort', 'oldest_due'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'asc'))),
        ];
        if ($rangeMonths > 0 && ($filters['from'] === '' || $filters['to'] === '')) {
            $filters['from'] = $rangeFrom->toDateString();
            $filters['to'] = $rangeTo->toDateString();
        }
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $today = now()->startOfDay();
        $balanceSnapshot = app(FinanceBalanceSnapshotService::class);
        $baseFilters = array_merge($filters, ['aging' => '', 'workflow' => '']);
        $allInvoices = $this->buildArrearsInvoices($baseFilters)->limit(5000)->get();
        $invoices = $allInvoices->filter(
            fn (PmInvoice $invoice) => $this->arrearsInvoiceMatchesDisplayFilters($invoice, $filters, $today)
        )->values();
        $summaryInvoices = $allInvoices;
        $aggregated = $invoices
            ->filter(fn (PmInvoice $i) => (int) ($i->pm_tenant_id ?? 0) > 0)
            ->groupBy('pm_tenant_id')
            ->map(function ($group) use ($today, $balanceSnapshot) {
                /** @var \Illuminate\Support\Collection<int,PmInvoice> $group */
                $first = $group->first();
                $tenant = $first->tenant;
                $totalBalance = (float) $group->sum(fn (PmInvoice $i) => $balanceSnapshot->invoiceBalance($i));
                $oldestDue = $group->pluck('due_date')->filter()->min();
                $maxDaysOverdue = (int) $group->max(fn (PmInvoice $i) => $this->arrearsDaysOverdue($i->due_date, $today));
                $daysLate = $maxDaysOverdue > 0
                    ? $maxDaysOverdue
                    : ($oldestDue ? (int) $today->diffInDays($oldestDue->copy()->startOfDay(), true) : 0);
                $agingLabel = $maxDaysOverdue > 0
                    ? (string) $maxDaysOverdue
                    : $this->arrearsAgingLabel($oldestDue, $today);
                $workflow = $this->arrearsWorkflowForDaysOverdue($maxDaysOverdue);

                $units = $group
                    ->map(fn (PmInvoice $i) => trim(((string) ($i->unit?->property?->name ?? '')).' / '.((string) ($i->unit?->label ?? '')), ' /'))
                    ->filter(fn ($label) => $label !== '')
                    ->unique()
                    ->values();

                $types = $group
                    ->map(fn (PmInvoice $i) => strtoupper((string) ($i->invoice_type ?: 'rent')))
                    ->unique()
                    ->values()
                    ->all();

                $lastContact = $group->max('updated_at');
                if ($lastContact && ! $lastContact instanceof \Carbon\Carbon) {
                    $lastContact = \Carbon\Carbon::parse((string) $lastContact);
                }

                return [
                    'tenant_id' => (int) $first->pm_tenant_id,
                    'tenant_name' => (string) ($tenant?->name ?? '—'),
                    'tenant_phone' => (string) ($tenant?->phone ?? ''),
                    'tenant_email' => (string) ($tenant?->email ?? ''),
                    'tenant_account' => (string) ($tenant?->account_number ?? ''),
                    'invoice_count' => $group->count(),
                    'invoice_ids' => $group->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'units' => $units,
                    'types' => $types,
                    'oldest_due' => $oldestDue,
                    'days_late' => $daysLate,
                    'aging_label' => $agingLabel,
                    'balance' => $totalBalance,
                    'last_contact' => $lastContact,
                    'workflow' => $workflow,
                ];
            })
            ->values();

        $sortBy = in_array($filters['sort'], ['oldest_due', 'days_late', 'balance', 'tenant', 'last_contact', 'invoice_count'], true)
            ? $filters['sort']
            : 'oldest_due';
        $sortDir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'asc';
        $aggregated = $aggregated
            ->sortBy(function (array $row) use ($sortBy) {
                return match ($sortBy) {
                    'tenant' => mb_strtolower($row['tenant_name']),
                    'days_late' => (int) $row['days_late'],
                    'balance' => (float) $row['balance'],
                    'last_contact' => $row['last_contact']?->getTimestamp() ?? 0,
                    'invoice_count' => (int) $row['invoice_count'],
                    default => $row['oldest_due']?->getTimestamp() ?? PHP_INT_MAX,
                };
            }, SORT_REGULAR, $sortDir === 'desc')
            ->values();

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $exportRows = $aggregated;

            return TabularExport::stream(
                'arrears-'.now()->format('Ymd_His'),
                ['Tenant', 'Phone', 'Email', 'Account', 'Units', 'Invoices', 'Arrears types', 'Oldest due', 'Aging', 'Total balance', 'Last contact', 'Workflow'],
                function () use ($exportRows) {
                    foreach ($exportRows as $row) {
                        yield [
                            $row['tenant_name'],
                            $row['tenant_phone'],
                            $row['tenant_email'],
                            $row['tenant_account'],
                            $row['units']->implode(', '),
                            (string) $row['invoice_count'],
                            implode(', ', $row['types']),
                            $row['oldest_due']?->format('Y-m-d') ?? '',
                            (string) ($row['aging_label'] ?? $row['days_late']),
                            number_format($row['balance'], 2, '.', ''),
                            $row['last_contact']?->format('Y-m-d') ?? '',
                            $row['workflow'],
                        ];
                    }
                },
                $export
            );
        }

        $page = max(1, (int) $request->query('page', 1));
        $total = $aggregated->count();
        $sliced = $aggregated->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $summaryBuckets = [
            'not_due' => 0.0,
            '0_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over_90' => 0.0,
        ];
        foreach ($summaryInvoices as $i) {
            $balance = $balanceSnapshot->invoiceBalance($i);
            $balanceSnapshot->addBalanceToAgingBuckets($i->due_date, $balance, $summaryBuckets, $today);
        }

        $summaryTotal = (float) $summaryInvoices->sum(fn (PmInvoice $i) => $balanceSnapshot->invoiceBalance($i));
        $summaryOverdue = $summaryBuckets['0_30'] + $summaryBuckets['31_60'] + $summaryBuckets['61_90'] + $summaryBuckets['over_90'];
        $summaryInvoiceCount = $summaryInvoices->count();
        $summaryTenantCount = $summaryInvoices
            ->filter(fn (PmInvoice $i) => (int) ($i->pm_tenant_id ?? 0) > 0)
            ->pluck('pm_tenant_id')
            ->unique()
            ->count();
        $overdueTenantCount = $summaryInvoices
            ->filter(fn (PmInvoice $i) => $this->arrearsDaysOverdue($i->due_date, $today) > 0)
            ->pluck('pm_tenant_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->count();
        $summaryCollectedOnOpen = (float) $summaryInvoices->sum(fn (PmInvoice $i) => (float) $i->amount_paid);
        $overduePct = $summaryTotal > 0 ? round(($summaryOverdue / $summaryTotal) * 100) : 0;
        $avgPerTenant = $summaryTenantCount > 0 ? $summaryTotal / $summaryTenantCount : 0.0;

        $statsPrimary = [
            [
                'label' => 'Total tenant arrears',
                'value' => PropertyMoney::kes($summaryTotal),
                'hint' => $dueRangeLabel.' · billable open balances',
                'emphasis' => true,
            ],
            [
                'label' => 'Total overdue',
                'value' => PropertyMoney::kes($summaryOverdue),
                'hint' => $overduePct.'% of arrears · past due date',
                'emphasis' => true,
            ],
            [
                'label' => 'Not yet due',
                'value' => PropertyMoney::kes($summaryBuckets['not_due']),
                'hint' => 'Unpaid but due date still ahead',
            ],
            [
                'label' => 'Partially paid (open)',
                'value' => PropertyMoney::kes($summaryCollectedOnOpen),
                'hint' => 'Already collected on unpaid invoices',
            ],
            [
                'label' => 'Tenants with arrears',
                'value' => (string) $summaryTenantCount,
                'hint' => 'Distinct tenants · '.$dueRangeLabel,
            ],
            [
                'label' => 'Tenants overdue',
                'value' => (string) $overdueTenantCount,
                'hint' => 'At least one invoice past due',
            ],
            [
                'label' => 'Unpaid invoices',
                'value' => (string) $summaryInvoiceCount,
                'hint' => $dueRangeLabel,
            ],
            [
                'label' => 'Avg per tenant',
                'value' => PropertyMoney::kes($avgPerTenant),
                'hint' => 'Mean open balance',
            ],
        ];

        $statsAging = [
            [
                'label' => '0–30 days overdue',
                'value' => PropertyMoney::kes($summaryBuckets['0_30']),
                'hint' => 'Recent overdue',
            ],
            [
                'label' => '31–60 days',
                'value' => PropertyMoney::kes($summaryBuckets['31_60']),
                'hint' => 'Follow-up',
            ],
            [
                'label' => '61–90 days',
                'value' => PropertyMoney::kes($summaryBuckets['61_90']),
                'hint' => 'Escalate',
            ],
            [
                'label' => '90+ days',
                'value' => PropertyMoney::kes($summaryBuckets['over_90']),
                'hint' => 'Final notice',
            ],
        ];

        $statsTable = [
            [
                'label' => 'Rows in table',
                'value' => (string) $total,
                'hint' => 'After search / aging / workflow filters',
            ],
        ];

        $rows = $sliced->map(function (array $r) {
            $invoiceIdsCsv = implode(',', $r['invoice_ids']);
            $tenantLabel = e($r['tenant_name']);
            $selector = new HtmlString(
                '<label class="inline-flex items-center" data-row-ignore-click><input type="checkbox" class="property-bulk-row-checkbox arrears-invoice-pick h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" value="'.e($invoiceIdsCsv).'" data-invoice-count="'.(int) $r['invoice_count'].'" aria-label="Select tenant '.$tenantLabel.'" /><span class="sr-only">Select</span></label>'
            );
            $unitsList = $r['units'];
            $unitText = $unitsList->take(2)->implode(', ');
            if ($unitsList->count() > 2) {
                $unitText .= ' +'.($unitsList->count() - 2).' more';
            }
            if ($unitText === '') {
                $unitText = '—';
            }
            $invoiceCell = (int) $r['invoice_count'] === 1 ? '1 invoice' : $r['invoice_count'].' invoices';
            $typesText = $r['types'] === [] ? '—' : implode(', ', $r['types']);
            $phoneCell = $this->buildPhoneCell($r['tenant_phone']);
            $oldestDue = $r['oldest_due']?->format('Y-m-d') ?? '—';
            $lastContact = $r['last_contact']?->format('Y-m-d') ?? '—';

            $detailUrl = route('property.revenue.arrears.tenant', ['tenant' => $r['tenant_id']], false);
            $noticesUrl = route('property.tenants.notices', ['tenant_id' => $r['tenant_id'], 'view' => 1], false);
            $tenantCell = new HtmlString(
                '<a href="'.e($detailUrl).'" class="font-medium text-slate-800 hover:text-indigo-700">'.$tenantLabel.'</a>'
            );
            $actions = new HtmlString(
                '<div class="flex flex-wrap items-center gap-2 text-xs">'.
                '<a href="'.e($detailUrl).'" class="rounded-md bg-indigo-50 px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-100">View invoices</a>'.
                '<a href="'.e($noticesUrl).'" class="rounded-md border border-slate-200 px-2 py-1 font-medium text-slate-600 hover:bg-slate-50">Open notices</a>'.
                '</div>'
            );

            return [
                $selector,
                $tenantCell,
                $phoneCell,
                $unitText,
                $invoiceCell,
                $typesText,
                $oldestDue,
                (string) ($r['aging_label'] ?? $r['days_late']),
                PropertyMoney::kes((float) $r['balance']),
                $lastContact,
                $r['workflow'],
                $actions,
            ];
        })->all();

        $grandTotalBalance = (float) $aggregated->sum(fn (array $r) => (float) $r['balance']);
        $grandTotalInvoices = (int) $aggregated->sum(fn (array $r) => (int) $r['invoice_count']);
        $invoiceTotalLabel = $grandTotalInvoices === 1 ? '1 invoice' : $grandTotalInvoices.' invoices';
        $tenantTotalLabel = $total === 1 ? '1 tenant' : $total.' tenants';
        $tableFooterRow = [
            '',
            new HtmlString('<span class="font-semibold text-slate-900 dark:text-white">Totals</span>'),
            '',
            '',
            new HtmlString(
                '<span>'.$invoiceTotalLabel.'</span>'.
                '<span class="block text-xs font-normal text-slate-500 dark:text-slate-400">'.$tenantTotalLabel.'</span>'
            ),
            '',
            '',
            '',
            new HtmlString('<span class="font-semibold text-rose-700 dark:text-rose-400">'.PropertyMoney::kes($grandTotalBalance).'</span>'),
            '',
            '',
            '',
        ];

        $reminderTargets = $invoices
            ->filter(fn (PmInvoice $i) => (int) ($i->pm_tenant_id ?? 0) > 0)
            ->take(500)
            ->map(fn (PmInvoice $i) => [
                'id' => (int) $i->id,
                'label' => (string) ($i->invoice_no.' · '.($i->tenant->name ?? 'Tenant').' · '.$i->due_date?->format('Y-m-d')),
            ])
            ->values()
            ->all();

        return property_view('property.agent.revenue.arrears', [
            'stats' => $statsPrimary,
            'statsPrimary' => $statsPrimary,
            'statsAging' => $statsAging,
            'statsTable' => $statsTable,
            'dueRangeLabel' => $dueRangeLabel,
            'columns' => ['Pick', 'Tenant', 'Phone', 'Unit(s)', 'Invoices', 'Arrears types', 'Oldest due', 'Aging', 'Balance', 'Last contact', 'Workflow', 'Actions'],
            'tableRows' => $rows,
            'tableFooterRow' => $tableFooterRow,
            'paginator' => $paginator,
            'perPage' => $perPage,
            'reminderTargets' => $reminderTargets,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $sortDir,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    /**
     * Per-tenant arrears detail. Lists every unpaid invoice for the
     * tenant with the same per-invoice "pick + send reminders" flow as
     * the legacy arrears index.
     */
    public function arrearsTenant(Request $request, PmTenant $tenant): View|StreamedResponse
    {
        $sortBy = strtolower(trim((string) $request->query('sort', 'due_date')));
        $sortDir = strtolower(trim((string) $request->query('dir', 'asc')));
        if (! in_array($sortBy, ['due_date', 'balance', 'invoice_no', 'updated_at', 'id'], true)) {
            $sortBy = 'due_date';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $orderColumn = $sortBy === 'balance' ? 'amount' : $sortBy;

        $invoices = $this->buildArrearsInvoices([])
            ->with(['unit.property'])
            ->where('pm_tenant_id', $tenant->id)
            ->reorder()
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('id')
            ->get();

        $today = now()->startOfDay();
        $balanceSnapshot = app(FinanceBalanceSnapshotService::class);
        $totalBalance = 0.0;
        $totalAmount = 0.0;
        $totalPaid = 0.0;

        $rows = $invoices->map(function (PmInvoice $i) use ($today, &$totalBalance, &$totalAmount, &$totalPaid, $balanceSnapshot) {
            $bal = $balanceSnapshot->invoiceBalance($i);
            $totalBalance += $bal;
            $totalAmount += (float) $i->amount;
            $totalPaid += (float) $i->amount_paid;
            $daysOverdue = $this->arrearsDaysOverdue($i->due_date, $today);
            $agingLabel = $this->arrearsAgingLabel($i->due_date, $today);
            $workflow = $this->arrearsWorkflowForDaysOverdue($daysOverdue);
            $type = strtoupper((string) ($i->invoice_type ?: 'rent'));
            $typeLabel = $type;
            if (is_string($i->description) && trim($i->description) !== '') {
                $typeLabel .= ' - '.trim($i->description);
            }
            $selector = new HtmlString(
                '<label class="inline-flex items-center" data-row-ignore-click><input type="checkbox" class="property-bulk-row-checkbox arrears-invoice-pick h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" value="'.(int) $i->id.'" aria-label="Select invoice '.e((string) $i->invoice_no).'" /><span class="sr-only">Select</span></label>'
            );
            $invoiceLink = new HtmlString(
                '<a href="'.e(route('property.revenue.invoices.show', ['invoice' => $i->id], false)).'" class="text-indigo-600 hover:text-indigo-700 font-medium">'.e((string) ($i->invoice_no ?? '—')).'</a>'
            );

            return [
                $selector,
                $invoiceLink,
                trim(((string) ($i->unit?->property?->name ?? 'Unknown property')).' / '.((string) ($i->unit?->label ?? 'Unknown unit')), ' /'),
                $typeLabel,
                $i->issue_date?->format('Y-m-d') ?? '—',
                $i->due_date?->format('Y-m-d') ?? '—',
                $agingLabel,
                PropertyMoney::kes((float) $i->amount),
                PropertyMoney::kes((float) $i->amount_paid),
                PropertyMoney::kes($bal),
                $i->updated_at?->format('Y-m-d') ?? '—',
                $workflow,
            ];
        })->all();

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $exportInvoices = $invoices;

            return TabularExport::stream(
                'arrears-tenant-'.($tenant->name ? \Illuminate\Support\Str::slug($tenant->name) : (string) $tenant->id).'-'.now()->format('Ymd_His'),
                ['Invoice', 'Unit', 'Type', 'Issued', 'Due', 'Aging', 'Amount', 'Paid', 'Balance', 'Last update', 'Workflow'],
                function () use ($exportInvoices, $today, $balanceSnapshot) {
                    foreach ($exportInvoices as $i) {
                        $bal = $balanceSnapshot->invoiceBalance($i);
                        $daysOverdue = $this->arrearsDaysOverdue($i->due_date, $today);
                        $workflow = $this->arrearsWorkflowForDaysOverdue($daysOverdue);
                        yield [
                            (string) ($i->invoice_no ?? ''),
                            (string) (($i->unit?->property?->name ?? '').'/'.($i->unit?->label ?? '')),
                            strtoupper((string) ($i->invoice_type ?: 'rent')),
                            $i->issue_date?->format('Y-m-d') ?? '',
                            $i->due_date?->format('Y-m-d') ?? '',
                            $this->arrearsAgingLabel($i->due_date, $today),
                            number_format((float) $i->amount, 2, '.', ''),
                            number_format((float) $i->amount_paid, 2, '.', ''),
                            number_format($bal, 2, '.', ''),
                            $i->updated_at?->format('Y-m-d') ?? '',
                            $workflow,
                        ];
                    }
                },
                $export
            );
        }

        $oldestDue = $invoices->pluck('due_date')->filter()->min();
        $maxDaysOverdue = (int) $invoices->max(fn (PmInvoice $i) => $this->arrearsDaysOverdue($i->due_date, $today));
        $maxDays = $maxDaysOverdue > 0
            ? $maxDaysOverdue
            : ($oldestDue ? (int) $today->diffInDays($oldestDue->copy()->startOfDay(), true) : 0);
        $workflow = $this->arrearsWorkflowForDaysOverdue($maxDaysOverdue);

        $tableFooterRow = [
            '',
            new HtmlString('<span class="font-semibold text-slate-900 dark:text-white">Totals</span>'),
            '',
            '',
            '',
            '',
            '',
            PropertyMoney::kes($totalAmount),
            PropertyMoney::kes($totalPaid),
            new HtmlString('<span class="font-semibold text-rose-700 dark:text-rose-400">'.PropertyMoney::kes($totalBalance).'</span>'),
            '',
            '',
        ];

        $reminderTargets = $invoices
                ->map(fn (PmInvoice $i) => [
                    'id' => (int) $i->id,
                'label' => (string) ($i->invoice_no.' · '.$i->due_date?->format('Y-m-d')),
                ])
                ->values()
            ->all();

        return property_view('property.agent.revenue.arrears_tenant', [
            'tenant' => $tenant,
            'tableRows' => $rows,
            'tableFooterRow' => $tableFooterRow,
            'columns' => ['Pick', 'Invoice', 'Unit', 'Arrears type', 'Issued', 'Due', 'Aging', 'Amount', 'Paid', 'Balance', 'Last update', 'Workflow'],
            'reminderTargets' => $reminderTargets,
            'summary' => [
                'invoice_count' => $invoices->count(),
                'total_balance' => $totalBalance,
                'oldest_due' => $oldestDue?->format('Y-m-d') ?? '—',
                'days_late' => $maxDays,
                'aging_label' => $maxDaysOverdue > 0
                    ? (string) $maxDaysOverdue
                    : $this->arrearsAgingLabel($oldestDue, $today),
                'workflow' => $workflow,
                'last_contact' => $invoices->max('updated_at')?->format('Y-m-d') ?? '—',
            ],
            'filters' => [
                'sort' => $sortBy,
                'dir' => $sortDir,
            ],
        ]);
    }

    /**
     * Build the base PmInvoice query that drives both the arrears index
     * and the per-tenant detail view. Centralised so filtering rules stay
     * in lockstep.
     *
     * @param  array<string, string>  $filters
     */
    private function buildArrearsInvoices(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $today = now()->toDateString();

        $query = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->withOutstandingBalance()
            ->where(function (\Illuminate\Database\Eloquent\Builder $inner) {
                $inner->whereNull('invoice_kind')
                    ->orWhere('invoice_kind', PmInvoice::KIND_INVOICE);
            });

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('invoice_no', 'like', '%'.$q.'%')
                    ->orWhereHas('tenant', fn ($tq) => $tq
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%'))
                    ->orWhereHas('unit', fn ($uq) => $uq
                        ->where('label', 'like', '%'.$q.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$q.'%')));
            });
        }

        $aging = strtolower((string) ($filters['aging'] ?? ''));
        if ($aging === 'overdue') {
            $query->whereDate('due_date', '<', $today);
        } elseif ($aging === 'not_due') {
            $query->whereDate('due_date', '>=', $today);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('due_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('due_date', '<=', $filters['to']);
        }

        $workflow = strtolower((string) ($filters['workflow'] ?? ''));
        if (in_array($workflow, ['reminder', 'follow-up', 'escalated'], true)) {
            if ($workflow === 'escalated') {
                $query->whereRaw('DATEDIFF(?, due_date) >= 30', [$today]);
            } elseif ($workflow === 'follow-up') {
                $query->whereRaw('DATEDIFF(?, due_date) >= 14', [$today])
                    ->whereRaw('DATEDIFF(?, due_date) < 30', [$today]);
            } else {
                // Reminder: upcoming unpaid OR overdue under 14 days.
                $query->where(function (\Illuminate\Database\Eloquent\Builder $inner) use ($today) {
                    $inner->whereDate('due_date', '>=', $today)
                        ->orWhere(function (\Illuminate\Database\Eloquent\Builder $overdue) use ($today) {
                            $overdue->whereRaw('DATEDIFF(?, due_date) >= 0', [$today])
                                ->whereRaw('DATEDIFF(?, due_date) < 14', [$today]);
                        });
                });
            }
        }

        return $query->orderBy('due_date')->orderBy('id');
    }

    public function sendArrearsReminders(
        Request $request,
        BulkSmsService $sms,
        TenantCommunicationStageService $stageService,
        PropertyCommunicationTemplateService $templateService,
        PropertyAgentContactResolver $agentContacts,
    ): RedirectResponse {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email,both'],
            'template_key' => ['required', 'in:friendly,firm,final'],
            'target_mode' => ['nullable', 'in:all,single,selected'],
            'single_invoice_id' => ['nullable', 'integer', 'exists:pm_invoices,id'],
            'selected_invoice_ids' => ['nullable', 'array'],
            'selected_invoice_ids.*' => ['integer', 'exists:pm_invoices,id'],
            'selected_invoice_ids_raw' => ['nullable', 'string'],
        ]);

        $targetMode = (string) ($data['target_mode'] ?? 'all');
        $singleInvoiceId = (int) ($data['single_invoice_id'] ?? 0);
        $selectedFromArray = collect((array) ($data['selected_invoice_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0);
        $selectedFromRaw = collect(preg_split('/[\s,;]+/', (string) ($data['selected_invoice_ids_raw'] ?? '')) ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();
        $selectedInvoiceIds = $selectedFromArray
            ->merge($selectedFromRaw)
            ->unique()
            ->values()
            ->all();

        $invoicesQuery = $this->buildArrearsInvoices([])
            ->with(['tenant:id,name,email,phone', 'unit:id,label,property_id', 'unit.property:id,name']);

        if ($targetMode === 'single') {
            if ($singleInvoiceId <= 0) {
                return back()->withErrors([
                    'single_invoice_id' => 'Choose an invoice for single reminder.',
                ])->withInput();
            }
            $invoicesQuery->where('id', $singleInvoiceId);
        } elseif ($targetMode === 'selected') {
            if ($selectedInvoiceIds === []) {
                return back()->withErrors([
                    'selected_invoice_ids' => 'Select one or more arrears rows first.',
                ])->withInput();
            }
            $invoicesQuery->whereIn('id', $selectedInvoiceIds);
        }

        $invoices = $invoicesQuery
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $sentSms = 0;
        $sentEmail = 0;
        $failed = 0;
        $today = now()->toDateString();
        $failedReasons = [];
        $skippedReasons = [];

        $addFailedReason = static function (string $reason) use (&$failedReasons): void {
            $failedReasons[$reason] = (int) ($failedReasons[$reason] ?? 0) + 1;
        };
        $addSkippedReason = static function (string $reason) use (&$skippedReasons): void {
            $skippedReasons[$reason] = (int) ($skippedReasons[$reason] ?? 0) + 1;
        };

        foreach ($invoices as $inv) {
            $tenant = $inv->tenant;
            if (! $tenant) {
                $addSkippedReason('missing tenant');

                continue;
            }

            $balance = max(0.0, (float) $inv->amount - (float) $inv->amount_paid);
            if ($balance <= 0) {
                continue;
            }

            $dueDate = $inv->due_date?->toDateString();
            if (! $dueDate) {
                $addSkippedReason('missing due date');

                continue;
            }

            $daysOverdue = $this->arrearsDaysOverdue($inv->due_date, now()->startOfDay());
            $propertyUnit = trim((string) (($inv->unit?->property?->name ?? '—').'/'.($inv->unit?->label ?? '—')), '/');
            $stage = $stageService->resolveFromDueDate(
                $inv->due_date?->copy()->startOfDay() ?? now()->startOfDay(),
                now()->startOfDay()
            );
            if ($stage === null && $daysOverdue > 0) {
                $bucket = $stageService->bucketStageKeyForDaysOverdue($daysOverdue);
                $def = $stageService->stageDefinition($bucket) ?? [];
                $stage = [
                    'stage_key' => $bucket,
                    'internal_stage' => 'D+'.$daysOverdue,
                    'display_label' => (string) ($def['display_label'] ?? $bucket),
                    'sms_header' => (string) ($def['sms_header'] ?? 'RENT OVERDUE'),
                    'email_subject' => (string) ($def['email_subject'] ?? 'Rent overdue'),
                    'stage_message' => (string) ($def['stage_message'] ?? ''),
                    'days_overdue' => $daysOverdue,
                ];
            }
            if ($stage === null) {
                $addSkippedReason('no communication stage for due date');

                continue;
            }
            if ($data['template_key'] === 'final' && $daysOverdue >= 14) {
                $finalDef = $stageService->stageDefinition('FINAL_DEMAND') ?? [];
                $stage['stage_key'] = 'FINAL_DEMAND';
                $stage['display_label'] = (string) ($finalDef['display_label'] ?? $stage['display_label']);
                $stage['sms_header'] = (string) ($finalDef['sms_header'] ?? $stage['sms_header']);
                $stage['email_subject'] = (string) ($finalDef['email_subject'] ?? $stage['email_subject']);
                $stage['stage_message'] = (string) ($finalDef['stage_message'] ?? $stage['stage_message']);
            } elseif ($data['template_key'] === 'firm') {
                $stage['stage_message'] = trim((string) $stage['stage_message']).' Please treat this as urgent.';
            }

            $messageContext = $agentContacts->mergeIntoContext([
                'tenant_name' => (string) $tenant->name,
                'invoice_no' => (string) $inv->invoice_no,
                'unit_name' => $propertyUnit !== '' ? $propertyUnit : '—',
                'balance' => number_format($balance, 2),
                'due_date' => $dueDate,
                'stage' => $stage,
            ], $inv);
            $staffSubject = $stageService->staffSubjectLine([
                'internal_stage' => $stage['internal_stage'],
                'display_label' => $stage['display_label'],
                'invoice_no' => $inv->invoice_no,
            ]);
            $emailPack = $templateService->buildRentReminderEmail($messageContext);
            $smsBody = $templateService->resolveRentReminderSms($messageContext);
            $tenantEmail = strtolower(trim((string) ($tenant->email ?? '')));
            $noticeCreated = false;

            if (in_array($data['channel'], ['email', 'both'], true)) {
                if ($tenantEmail === '') {
                    $addSkippedReason('email missing');
                } elseif (! filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
                    $addSkippedReason('invalid email format');
                } else {
                    $alreadyEmailed = PmMessageLog::query()
                        ->where('channel', 'email')
                        ->where('subject', $staffSubject)
                        ->where('to_address', $tenantEmail)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (! $alreadyEmailed) {
                        try {
                            Mail::raw($emailPack['body'], function ($m) use ($tenantEmail, $emailPack) {
                                $m->to($tenantEmail)->subject((string) $emailPack['subject']);
                            });
                            PmMessageLog::query()->create([
                                'user_id' => $request->user()?->id,
                                'channel' => 'email',
                                'to_address' => $tenantEmail,
                                'subject' => $staffSubject,
                                'internal_stage' => $stage['internal_stage'],
                                'display_stage' => $stage['display_label'],
                                'template_category' => 'rent_reminder',
                                'body' => $emailPack['body'],
                                'delivery_status' => 'sent',
                                'sent_at' => now(),
                            ]);
                            $sentEmail++;
                            if (! $noticeCreated) {
                                $noticeCreated = $this->createArrearsNoticeIfMissing($inv, $emailPack['body'], $request->user()?->id, $stage);
                            }
                        } catch (\Throwable $e) {
                            $failed++;
                            $addFailedReason('email send error');
                            PmMessageLog::query()->create([
                                'user_id' => $request->user()?->id,
                                'channel' => 'email',
                                'to_address' => $tenantEmail,
                                'subject' => $staffSubject,
                                'internal_stage' => $stage['internal_stage'],
                                'display_stage' => $stage['display_label'],
                                'template_category' => 'rent_reminder',
                                'body' => $emailPack['body'],
                                'delivery_status' => 'failed',
                                'delivery_error' => 'Email failed: '.$e->getMessage(),
                                'sent_at' => null,
                            ]);
                            Log::warning('arrears_reminder_email_failed', [
                                'invoice_id' => $inv->id,
                                'invoice_no' => $inv->invoice_no,
                                'tenant_id' => $tenant->id,
                                'tenant_email' => $tenantEmail,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        $addSkippedReason('email already sent today');
                    }
                }
            }

            if (in_array($data['channel'], ['sms', 'both'], true)) {
                if (empty($tenant->phone)) {
                    $addSkippedReason('phone missing');
                } else {
                    $phones = $sms->normalizeRecipientList((string) $tenant->phone);
                    if ($phones !== []) {
                        $smsTo = implode(',', $phones);
                        $alreadySms = PmMessageLog::query()
                            ->where('channel', 'sms')
                            ->where('subject', $staffSubject)
                            ->where('to_address', $smsTo)
                            ->whereDate('created_at', $today)
                            ->exists();

                        if (! $alreadySms) {
                            $result = $sms->sendNow($smsBody, $phones, $request->user()?->id, null, 'property');
                            if (($result['ok'] ?? false) === true) {
                                PmMessageLog::query()->create([
                                    'user_id' => $request->user()?->id,
                                    'channel' => 'sms',
                                    'to_address' => $smsTo,
                                    'subject' => $staffSubject,
                                    'internal_stage' => $stage['internal_stage'],
                                    'display_stage' => $stage['display_label'],
                                    'template_category' => 'rent_reminder',
                                    'body' => $smsBody,
                                    'delivery_status' => 'sent',
                                    'sent_at' => now(),
                                ]);
                                $sentSms++;
                                if (! $noticeCreated) {
                                    $noticeCreated = $this->createArrearsNoticeIfMissing($inv, $smsBody, $request->user()?->id, $stage);
                                }
                            } else {
                                $failed++;
                                $addFailedReason('sms provider error');
                            }
                        } else {
                            $addSkippedReason('sms already sent today');
                        }
                    } else {
                        $addSkippedReason('invalid phone format');
                    }
                }
            }
        }

        $failedSummary = collect($failedReasons)
            ->map(fn (int $count, string $reason) => "{$reason}={$count}")
            ->implode(', ');
        $skippedSummary = collect($skippedReasons)
            ->map(fn (int $count, string $reason) => "{$reason}={$count}")
            ->implode(', ');

        $message = "Arrears reminders sent. SMS: {$sentSms}, Email: {$sentEmail}, Failed: {$failed}.";
        if ($failedSummary !== '') {
            $message .= " Failed reasons: {$failedSummary}.";
        }
        if ($skippedSummary !== '') {
            $message .= " Skipped: {$skippedSummary}.";
        }

        return back()->with('success', $message);
    }

    private function createArrearsNoticeIfMissing(PmInvoice $invoice, string $message, ?int $userId, ?array $stage = null): bool
    {
        $invoiceNo = (string) ($invoice->invoice_no ?? '');
        if ($invoiceNo === '') {
            return false;
        }
        $today = now()->toDateString();
        $needle = 'Invoice: '.$invoiceNo;
        $exists = PmTenantNotice::query()
            ->where('pm_tenant_id', (int) $invoice->pm_tenant_id)
            ->where('property_unit_id', (int) $invoice->property_unit_id)
            ->where('notice_type', 'arrears_reminder')
            ->whereDate('due_on', $today)
            ->where('notes', 'like', '%'.$needle.'%')
            ->exists();
        if ($exists) {
            return false;
        }

        $stageLines = '';
        if (is_array($stage)) {
            $stageLines = 'Internal stage: '.($stage['internal_stage'] ?? '—')."\n".
                'Display label: '.($stage['display_label'] ?? '—')."\n";
        }

        PmTenantNotice::query()->create([
            'pm_tenant_id' => (int) $invoice->pm_tenant_id,
            'property_unit_id' => (int) $invoice->property_unit_id,
            'notice_type' => 'arrears_reminder',
            'status' => 'sent',
            'due_on' => $today,
            'notes' => "Auto arrears reminder\n{$stageLines}Invoice: {$invoiceNo}\n\n{$message}",
            'created_by_user_id' => $userId,
        ]);

        return true;
    }

    public function sendArrearsTestEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        $to = trim((string) ($user?->email ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors([
                'arrears_test_email' => 'Your account email is missing or invalid. Update your profile email and retry.',
            ]);
        }

        $subject = '[ARREARS TEST] Mail diagnostics';
        $body = "This is a test email from arrears reminders.\n".
            'Sent at: '.now()->format('Y-m-d H:i:s')."\n".
            'User ID: '.(string) ($user?->id ?? 'n/a')."\n";

        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });

            PmMessageLog::query()->create([
                'user_id' => $user?->id,
                'channel' => 'email',
                'to_address' => $to,
                'subject' => $subject,
                'body' => $body,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            return back()->with('success', 'Test email sent to '.$to.'.');
        } catch (\Throwable $e) {
            Log::warning('arrears_test_email_failed', [
                'user_id' => $user?->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'arrears_test_email' => 'Test email failed: '.$e->getMessage(),
            ]);
        }
    }

    public function penalties(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'scope' => trim((string) $request->query('scope', '')),
            'sort' => strtolower(trim((string) $request->query('sort', 'name'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'asc'))),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $query = PmPenaltyRule::query();
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', '%'.$q.'%')
                    ->orWhere('scope', 'like', '%'.$q.'%')
                    ->orWhere('trigger_event', 'like', '%'.$q.'%')
                    ->orWhere('formula', 'like', '%'.$q.'%');
            });
        }
        if ($filters['status'] !== '' && in_array($filters['status'], ['active', 'off'], true)) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if ($filters['scope'] !== '') {
            $query->where('scope', $filters['scope']);
        }
        $sortMap = ['name' => 'name', 'scope' => 'scope', 'trigger_event' => 'trigger_event', 'effective_from' => 'effective_from', 'id' => 'id'];
        $sortBy = $sortMap[$filters['sort']] ?? 'name';
        $dir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'asc';
        $query->orderBy($sortBy, $dir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $exportRows = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'penalty-rules-'.now()->format('Ymd_His'),
                ['Rule name', 'Scope', 'Trigger', 'Formula', 'Cap', 'Effective', 'Status'],
                function () use ($exportRows) {
                    foreach ($exportRows as $r) {
                        $parts = [$r->formula];
                        if ($r->percent !== null) {
                            $parts[] = (string) $r->percent.'%';
                        }
                        if ($r->amount !== null) {
                            $parts[] = PropertyMoney::kes((float) $r->amount);
                        }

                        yield [
                            (string) $r->name,
                            (string) $r->scope,
                            (string) $r->trigger_event.' (grace '.$r->grace_days.'d)',
                            implode(' · ', array_filter($parts)),
                            $r->cap !== null ? PropertyMoney::kes((float) $r->cap) : '—',
                            $r->effective_from?->format('Y-m-d') ?? '—',
                            $r->is_active ? 'Active' : 'Off',
                        ];
                    }
                },
                $export
            );
        }

        $rules = (clone $query)->paginate($perPage)->withQueryString();
        $active = $rules->getCollection()->where('is_active', true);

        $rows = $rules->getCollection()->map(function (PmPenaltyRule $r) {
            $parts = [$r->formula, str_replace('_', ' ', (string) ($r->compounding_mode ?? 'simple'))];
            if ($r->percent !== null) {
                $parts[] = (string) $r->percent.'%';
            }
            if ($r->amount !== null) {
                $parts[] = PropertyMoney::kes((float) $r->amount);
            }

            return [
                $r->name,
                $r->scope,
                $r->trigger_event.' (grace '.$r->grace_days.'d)',
                implode(' · ', array_filter($parts)),
                ($r->cap !== null ? PropertyMoney::kes((float) $r->cap) : '—')
                    .($r->cumulative_cap !== null ? ' / cum '.PropertyMoney::kes((float) $r->cumulative_cap) : ''),
                $r->effective_from?->format('Y-m-d') ?? '—',
                $r->is_active ? 'Active' : 'Off',
            ];
        })->all();

        return property_view('property.agent.revenue.penalties', [
            'stats' => [
                ['label' => 'Rules', 'value' => (string) $rules->total(), 'hint' => 'Filtered total'],
                ['label' => 'Active', 'value' => (string) $active->count(), 'hint' => ''],
                ['label' => 'Applied (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => 'Posting not automated'],
                ['label' => 'Waived (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => ''],
            ],
            'columns' => ['Rule name', 'Scope', 'Trigger', 'Formula', 'Cap', 'Effective', 'Status'],
            'tableRows' => $rows,
            'penaltyRules' => $rules->getCollection(),
            'paginator' => $rules,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
            'scopes' => PmPenaltyRule::query()->select('scope')->distinct()->orderBy('scope')->pluck('scope')->values(),
        ]);
    }

    public function storePenaltyRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'scope' => ['required', 'string', 'max:64'],
            'trigger_event' => ['required', 'string', 'max:64'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'formula' => ['required', 'string', 'max:64'],
            'compounding_mode' => ['required', 'in:simple,daily_compound,one_shot'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cap' => ['nullable', 'numeric', 'min:0'],
            'cumulative_cap' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = PmPenaltyRule::query()->create([
            ...$data,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $warnings = app(PenaltyEngineService::class)->ruleOperatorWarnings($rule);
        $message = __('Penalty rule saved.');
        if ($warnings !== []) {
            $message .= ' Warning: '.implode(' ', $warnings);
        }

        return back()->with('success', $message);
    }

    public function destroyPenaltyRule(PmPenaltyRule $penalty_rule): RedirectResponse
    {
        $penalty_rule->delete();

        return back()->with('success', __('Rule removed.'));
    }

    public function receipts(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => strtolower(trim((string) $request->query('sort', 'updated_at'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'desc'))),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $query = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->where('status', PmInvoice::STATUS_PAID)
            ->whereNotNull('updated_at');
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('invoice_no', 'like', '%'.$q.'%')
                    ->orWhere('id', $q)
                    ->orWhereHas('tenant', fn ($tq) => $tq
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%'))
                    ->orWhereHas('unit', fn ($uq) => $uq
                        ->where('label', 'like', '%'.$q.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$q.'%')));
            });
        }
        if ($filters['from'] !== '') {
            $query->whereDate('updated_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('updated_at', '<=', $filters['to']);
        }
        $sortMap = ['updated_at' => 'updated_at', 'amount' => 'amount', 'invoice_no' => 'invoice_no', 'id' => 'id'];
        $sortBy = $sortMap[$filters['sort']] ?? 'updated_at';
        $dir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'desc';
        $query->orderBy($sortBy, $dir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $items = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'receipts-'.now()->format('Ymd_His'),
                ['Receipt #', 'Invoice', 'Tenant', 'Amount', 'Tax', 'Submitted', 'eTIMS status'],
                function () use ($items) {
                    foreach ($items as $i) {
                        yield [
                            'RCP-'.$i->id,
                            (string) $i->invoice_no,
                            (string) ($i->tenant->name ?? ''),
                            PropertyMoney::kes((float) $i->amount),
                            'KES 0.00',
                            $i->updated_at?->format('Y-m-d') ?? '',
                            'Stub',
                        ];
                    }
                },
                $export
            );
        }

        $invoices = (clone $query)->paginate($perPage)->withQueryString();

        $stats = [
            ['label' => 'Paid invoices', 'value' => (string) $invoices->total(), 'hint' => 'Filtered total'],
            ['label' => 'eTIMS linked', 'value' => '0', 'hint' => 'Integration pending'],
            ['label' => 'Failed', 'value' => '0', 'hint' => ''],
        ];

        $rows = $invoices->getCollection()->map(fn (PmInvoice $i) => [
            'RCP-'.$i->id,
            (string) ($i->invoice_no ?? '—'),
            (string) ($i->tenant?->name ?? '—'),
            PropertyMoney::kes((float) $i->amount),
            'KES 0.00',
            $i->updated_at?->format('Y-m-d') ?? '—',
            'Stub',
            new HtmlString('<a href="'.route('property.revenue.invoices.show', ['invoice' => $i->id], false).'" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>'),
        ])->all();

        return property_view('property.agent.revenue.receipts', [
            'stats' => $stats,
            'columns' => ['Receipt #', 'Invoice', 'Tenant', 'Amount', 'Tax', 'Submitted', 'eTIMS status', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $invoices,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    /**
     * @param  array<int,mixed>  $rows
     */
    private function paginateRows(array $rows, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = count($rows);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);

        return (new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        ))->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function arrearsInvoiceMatchesDisplayFilters(PmInvoice $invoice, array $filters, \Carbon\Carbon $today): bool
    {
        $aging = strtolower(trim((string) ($filters['aging'] ?? '')));
        if ($aging === 'overdue') {
            if (! $invoice->due_date || ! $invoice->due_date->copy()->startOfDay()->lt($today)) {
                return false;
            }
        } elseif ($aging === 'not_due') {
            if (! $invoice->due_date || $invoice->due_date->copy()->startOfDay()->lt($today)) {
                return false;
            }
        }

        $workflow = strtolower(trim((string) ($filters['workflow'] ?? '')));
        if ($workflow === '') {
            return true;
        }

        $daysOverdue = $this->arrearsDaysOverdue($invoice->due_date, $today);

        return match ($workflow) {
            'escalated' => $daysOverdue >= 30,
            'follow-up' => $daysOverdue >= 14 && $daysOverdue < 30,
            'reminder' => $invoice->due_date && (
                $invoice->due_date->copy()->startOfDay()->gte($today)
                || ($daysOverdue >= 0 && $daysOverdue < 14)
            ),
            default => true,
        };
    }

    private function arrearsDaysOverdue(?\Carbon\CarbonInterface $dueDate, \Carbon\Carbon $today): int
    {
        if (! $dueDate) {
            return 0;
        }

        $due = \Carbon\Carbon::parse($dueDate)->startOfDay();
        if ($due->gte($today)) {
            return 0;
        }

        return (int) $due->diffInDays($today, true);
    }

    private function arrearsAgingLabel(?\Carbon\CarbonInterface $dueDate, \Carbon\Carbon $today): string
    {
        if (! $dueDate) {
            return '—';
        }

        $stage = app(TenantCommunicationStageService::class)->resolveFromDueDate(
            \Carbon\Carbon::parse($dueDate)->startOfDay(),
            $today->copy()->startOfDay()
        );

        return $stage['display_label'] ?? '—';
    }

    private function arrearsWorkflowForDaysOverdue(int $daysOverdue): string
    {
        return $daysOverdue >= 30 ? 'Escalated' : ($daysOverdue >= 14 ? 'Follow-up' : 'Reminder');
    }

    /**
     * @param  array{not_due: float, 0_30: float, 31_60: float, 61_90: float, over_90: float}  $buckets
     */
    /**
     * @return array{0: int, 1: string, 2: Carbon, 3: Carbon, 4: string}
     */
    private function resolveArrearsDueRange(Request $request): array
    {
        $allowed = [0, 1, 2, 3, 6, 12];
        $rangeMonths = (int) $request->query('range_months', 0);
        if (! in_array($rangeMonths, $allowed, true)) {
            $rangeMonths = 0;
        }

        $rangeEndYm = trim((string) $request->query('range_end', now()->format('Y-m')));
        if (preg_match('/^\d{4}\-\d{2}$/', $rangeEndYm) !== 1) {
            $rangeEndYm = now()->format('Y-m');
        }

        $rangeTo = Carbon::createFromFormat('Y-m', $rangeEndYm)->endOfMonth()->startOfDay();
        $rangeFrom = $rangeTo->copy()->subMonths(max(0, $rangeMonths - 1))->startOfMonth()->startOfDay();

        $dueRangeLabel = match ($rangeMonths) {
            0 => 'All due dates',
            1 => 'Due '.$rangeFrom->format('M Y'),
            default => 'Due '.$rangeFrom->format('M Y').' – '.$rangeTo->format('M Y').' ('.$rangeMonths.' mo)',
        };

        return [$rangeMonths, $rangeEndYm, $rangeFrom, $rangeTo, $dueRangeLabel];
    }

    private function addToArrearsBuckets(?\Carbon\CarbonInterface $dueDate, \Carbon\Carbon $today, float $balance, array &$buckets): void
    {
        if ($balance <= 0) {
            return;
        }

        if (! $dueDate || \Carbon\Carbon::parse($dueDate)->startOfDay()->gte($today)) {
            $buckets['not_due'] += $balance;

            return;
        }

        $days = $this->arrearsDaysOverdue($dueDate, $today);
        if ($days < 31) {
            $buckets['0_30'] += $balance;
        } elseif ($days < 61) {
            $buckets['31_60'] += $balance;
        } elseif ($days < 91) {
            $buckets['61_90'] += $balance;
        } else {
            $buckets['over_90'] += $balance;
        }
    }

    /**
     * Renders a phone column cell for the arrears table:
     *  - normalises Kenyan numbers to E.164 (+254...) for the tel: link
     *  - keeps the human-readable form on screen
     *  - falls back to a dash when no phone is on file
     */
    private function buildPhoneCell(string $rawPhone): HtmlString|string
    {
        $display = trim($rawPhone);
        if ($display === '') {
            return '—';
        }

        // Strip everything except digits and a leading +.
        $digits = preg_replace('/[^\d+]/', '', $display) ?? '';
        $tel = $digits;
        if ($tel !== '' && $tel[0] !== '+') {
            // Common KE formats: 07XXXXXXXX, 7XXXXXXXX, 254XXXXXXXXX.
            if (str_starts_with($tel, '254') && strlen($tel) >= 12) {
                $tel = '+'.$tel;
            } elseif (str_starts_with($tel, '0') && strlen($tel) === 10) {
                $tel = '+254'.substr($tel, 1);
            } elseif (strlen($tel) === 9) {
                $tel = '+254'.$tel;
            } else {
                $tel = '+'.$tel;
            }
        }

        // Without a usable number, just show the raw text.
        if ($tel === '+' || strlen($tel) < 8) {
            return e($display);
        }

        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block -mt-0.5 mr-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 3a1 1 0 0 1 1-1h2.153a1 1 0 0 1 .986.836l.74 4.435a1 1 0 0 1-.54 1.06l-1.548.773a11.037 11.037 0 0 0 6.105 6.105l.774-1.548a1 1 0 0 1 1.059-.54l4.435.74a1 1 0 0 1 .836.986V17a1 1 0 0 1-1 1h-2C7.82 18 2 12.18 2 5V3Z"/></svg>';

        return new HtmlString(
            '<a href="tel:'.e($tel).'" class="inline-flex items-center text-emerald-700 hover:text-emerald-800 font-medium" title="Call '.e($display).'">'
            .$icon.e($display).'</a>'
        );
    }
}
