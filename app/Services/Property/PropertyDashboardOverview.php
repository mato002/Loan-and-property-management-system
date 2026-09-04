<?php

namespace App\Services\Property;

use App\Models\Concerns\AgentWorkspaceScope;
use App\Support\Property\LandlordWorkspaceScope;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\PmMessageLog;
use App\Models\PmVendor;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\UnassignedPayment;
use App\Models\User;
use App\Services\BulkSmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PropertyDashboardOverview
{
    private const CACHE_TTL_SECONDS = 60;

    private const HEAVY_CACHE_TTL_SECONDS = 300;

    /**
     * @return array<string, mixed>
     */
    public static function forAgent(): array
    {
        return array_merge(self::lightForAgent(), self::heavyForAgent());
    }

    /**
     * Fast counts and portfolio KPIs â€” safe to render on every Turbo navigation.
     *
     * @return array<string, mixed>
     */
    public static function lightForAgent(): array
    {
        $userId = (int) (Auth::id() ?? 0);
        $scoped = AgentWorkspaceScope::shouldApply();
        $cacheKey = PropertyDashboardCache::lightKey($userId, $scoped);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, static fn () => self::buildLightForAgent());
    }

    /**
     * Lightweight lazy-load payload: KPIs, charts, and two short activity lists.
     *
     * @return array<string, mixed>
     */
    public static function metricsForAgent(): array
    {
        $userId = (int) (Auth::id() ?? 0);
        $scoped = AgentWorkspaceScope::shouldApply();
        $cacheKey = PropertyDashboardCache::heavyKey($userId, $scoped).':metrics';

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, static fn () => self::buildMetricsForAgent());
    }

    /**
     * Financial aggregates, charts, SMS provider calls, and activity tables.
     *
     * @return array<string, mixed>
     */
    public static function heavyForAgent(): array
    {
        $userId = (int) (Auth::id() ?? 0);
        $scoped = AgentWorkspaceScope::shouldApply();
        $cacheKey = PropertyDashboardCache::heavyKey($userId, $scoped);

        return Cache::remember($cacheKey, self::HEAVY_CACHE_TTL_SECONDS, static fn () => self::buildHeavyForAgent());
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildLightForAgent(): array
    {
        $year = (int) now()->year;
        $operationalPropertyIds = Property::query()->operational()->select('id');

        $properties = Property::query()->operational()->count();
        $unitsTotal = PropertyUnit::query()->whereIn('property_id', $operationalPropertyIds)->count();
        $unitsOccupied = PropertyUnit::query()->whereIn('property_id', $operationalPropertyIds)->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $unitsVacant = PropertyUnit::query()->whereIn('property_id', $operationalPropertyIds)->where('status', PropertyUnit::STATUS_VACANT)->count();
        $tenants = PmTenant::query()->count();
        $leasesActive = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->whereIn('property_id', $operationalPropertyIds))
            ->count();
        $leasesExpiring = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->whereIn('property_id', $operationalPropertyIds))
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->count();

        $maintOpen = PmMaintenanceRequest::query()->where('status', 'open')->count();
        $maintInProgress = PmMaintenanceRequest::query()->where('status', 'in_progress')->count();
        $vendorsActive = PmVendor::query()->where('status', 'active')->count();
        $applyAgentFilter = AgentWorkspaceScope::shouldApply();
        $agentUserId = $applyAgentFilter ? (int) Auth::id() : null;
        $landlordStats = self::landlordWorkspaceStats($applyAgentFilter, $agentUserId);

        $linkedLandlordsQuery = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id');
        if ($applyAgentFilter && $agentUserId) {
            $linkedLandlordsQuery->where('p.agent_user_id', $agentUserId);
        }
        $linkedLandlords = (int) $linkedLandlordsQuery->distinct('pl.user_id')->count('pl.user_id');

        $linkedProperties = (int) Property::query()->has('landlords')->count();
        $propertiesWithoutLandlord = max(0, $properties - $linkedProperties);
        $unmatchedBankPayments = Schema::hasTable('unassigned_payments')
            ? (int) UnassignedPayment::query()->count()
            : 0;

        $kpis = [
            [
                'label' => 'Properties',
                'value' => (string) $properties,
                'icon' => 'fa-building',
                'route' => 'property.properties.list',
                'bar' => 'bg-sky-500',
            ],
            [
                'label' => 'Total units',
                'value' => (string) $unitsTotal,
                'icon' => 'fa-door-open',
                'route' => 'property.properties.units',
                'bar' => 'bg-cyan-500',
            ],
            [
                'label' => 'Occupied units',
                'value' => (string) $unitsOccupied,
                'icon' => 'fa-house-chimney-user',
                'route' => 'property.properties.occupancy',
                'bar' => 'bg-emerald-500',
            ],
            [
                'label' => 'Vacant units',
                'value' => (string) $unitsVacant,
                'icon' => 'fa-house-circle-exclamation',
                'route' => 'property.properties.occupancy',
                'bar' => 'bg-amber-500',
            ],
            [
                'label' => 'Tenants',
                'value' => (string) $tenants,
                'icon' => 'fa-users',
                'route' => 'property.tenants.directory',
                'bar' => 'bg-violet-500',
            ],
            [
                'label' => 'Active leases',
                'value' => (string) $leasesActive,
                'icon' => 'fa-file-contract',
                'route' => 'property.tenants.leases',
                'bar' => 'bg-indigo-500',
            ],
            [
                'label' => 'Leases expiring (60d)',
                'value' => (string) $leasesExpiring,
                'icon' => 'fa-calendar-days',
                'route' => 'property.tenants.leases',
                'bar' => 'bg-orange-500',
            ],
            [
                'label' => 'Open maintenance',
                'value' => (string) ($maintOpen + $maintInProgress),
                'icon' => 'fa-screwdriver-wrench',
                'route' => 'property.maintenance.requests',
                'bar' => 'bg-slate-500',
            ],
            [
                'label' => 'Landlords',
                'value' => (string) $landlordStats['landlord_users'],
                'icon' => 'fa-user-tie',
                'route' => 'property.landlords.index',
                'bar' => 'bg-fuchsia-500',
            ],
            [
                'label' => 'Unlinked properties',
                'value' => (string) $propertiesWithoutLandlord,
                'icon' => 'fa-link-slash',
                'route' => 'property.properties.list',
                'bar' => 'bg-yellow-500',
            ],
            [
                'label' => 'Active vendors',
                'value' => (string) $vendorsActive,
                'icon' => 'fa-truck-field',
                'route' => 'property.vendors.directory',
                'bar' => 'bg-blue-600',
            ],
            [
                'label' => 'Unmatched bank payments',
                'value' => (string) $unmatchedBankPayments,
                'icon' => 'fa-building-columns',
                'route' => 'property.equity.unmatched',
                'bar' => 'bg-amber-600',
            ],
        ];

        return [
            'kpis' => $kpis,
            'chartYear' => $year,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildMetricsForAgent(): array
    {
        $year = (int) now()->year;
        $mtdCollected = PropertyDashboardStats::mtdCollected();
        $mtdBilled = PropertyDashboardStats::mtdBilled();
        $openBalance = PropertyDashboardStats::outstandingBalance();
        $commissionService = app(AgentCommissionService::class);
        $mtdStart = now()->startOfMonth();
        $mtdEnd = now()->endOfMonth();
        $mtdCommissionTotals = $commissionService->aggregate($mtdStart, $mtdEnd)['totals'];
        $commissionByProperty = $commissionService->chartByProperty($mtdStart, $mtdEnd, 5);
        if (($commissionByProperty['values'] ?? []) === []) {
            $commissionByProperty = $commissionService->collectionsChartByProperty($mtdStart, $mtdEnd, 5);
            $commissionByProperty['fallback'] = 'collections';
        }
        $commissionSplit = $commissionService->chartSplit($mtdStart, $mtdEnd);
        if (array_sum($commissionSplit['values'] ?? []) <= 0 && $mtdCollected > 0) {
            $commissionSplit = [
                'labels' => ['Collected (MTD)', 'Still to collect from billed'],
                'values' => [
                    round($mtdCollected, 2),
                    round(max(0.0, $mtdBilled - $mtdCollected), 2),
                ],
                'fallback' => 'collections',
            ];
        }

        $operationalPropertyIds = Property::query()->operational()->select('id');
        $unitsOccupied = PropertyUnit::query()->whereIn('property_id', $operationalPropertyIds)->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $unitsVacant = PropertyUnit::query()->whereIn('property_id', $operationalPropertyIds)->where('status', PropertyUnit::STATUS_VACANT)->count();
        $chartOccupancy = [
            'labels' => ['Occupied', 'Vacant'],
            'values' => [(float) $unitsOccupied, (float) $unitsVacant],
        ];
        $chartCollectionsBilled = [
            'labels' => ['Collected (MTD)', 'Billed not yet collected'],
            'values' => [
                round($mtdCollected, 2),
                round(max(0.0, $mtdBilled - $mtdCollected), 2),
            ],
        ];

        $financialKpis = [
            [
                'label' => 'Collections (MTD)',
                'value' => PropertyMoney::kes($mtdCollected),
                'icon' => 'fa-sack-dollar',
                'route' => 'property.revenue.payments',
                'bar' => 'bg-green-600',
            ],
            [
                'label' => 'Your commission (MTD)',
                'value' => PropertyMoney::kes($mtdCommissionTotals['commission']),
                'icon' => 'fa-percent',
                'route' => 'property.financials.commission',
                'bar' => 'bg-indigo-600',
            ],
            [
                'label' => 'Landlord net (MTD)',
                'value' => PropertyMoney::kes($mtdCommissionTotals['landlord_net']),
                'icon' => 'fa-hand-holding-dollar',
                'route' => 'property.financials.commission',
                'bar' => 'bg-violet-500',
            ],
            [
                'label' => 'Billed (MTD)',
                'value' => PropertyMoney::kes($mtdBilled),
                'icon' => 'fa-file-invoice-dollar',
                'route' => 'property.revenue.invoices',
                'bar' => 'bg-teal-500',
            ],
            [
                'label' => 'Tenant arrears',
                'value' => PropertyMoney::kes($openBalance),
                'icon' => 'fa-scale-unbalanced',
                'route' => 'property.revenue.arrears',
                'bar' => 'bg-rose-500',
            ],
        ];

        $invoiceByMonth = PmInvoice::query()
            ->whereYear('issue_date', $year)
            ->selectRaw('MONTH(issue_date) as month_num, COALESCE(SUM(amount), 0) as total')
            ->groupByRaw('MONTH(issue_date)')
            ->pluck('total', 'month_num');

        $paymentByMonth = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereNotNull('paid_at')
            ->whereYear('paid_at', $year)
            ->selectRaw('MONTH(paid_at) as month_num, COALESCE(SUM(amount), 0) as total')
            ->groupByRaw('MONTH(paid_at)')
            ->pluck('total', 'month_num');

        $chartLabels = [];
        $chartInvoices = [];
        $chartPayments = [];
        for ($m = 1; $m <= 12; $m++) {
            $chartLabels[] = Carbon::createFromDate($year, $m, 1)->format('M');
            $chartInvoices[] = (float) ($invoiceByMonth[$m] ?? 0);
            $chartPayments[] = (float) ($paymentByMonth[$m] ?? 0);
        }

        $recentRequests = PmMaintenanceRequest::query()
            ->with(['unit.property'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (PmMaintenanceRequest $r) {
                $u = $r->unit;
                $unitLabel = $u && $u->property
                    ? $u->property->name.' / '.$u->label
                    : '—';

                return [
                    'summary' => Str::limit($r->category.': '.$r->description, 48),
                    'unit' => $unitLabel,
                    'status' => ucfirst(str_replace('_', ' ', $r->status)),
                ];
            })
            ->all();

        $recentPayments = PmPayment::query()
            ->with('tenant')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (PmPayment $p) {
                return [
                    'tenant' => $p->tenant?->name ?? '—',
                    'amount' => PropertyMoney::kes((float) $p->amount),
                    'date' => $p->paid_at?->format('d M Y') ?? '—',
                ];
            })
            ->all();

        return [
            'financialKpis' => $financialKpis,
            'chartYear' => $year,
            'chartLabels' => $chartLabels,
            'chartInvoices' => $chartInvoices,
            'chartPayments' => $chartPayments,
            'chartCommissionByProperty' => $commissionByProperty,
            'chartCommissionSplit' => $commissionSplit,
            'chartOccupancy' => $chartOccupancy,
            'chartCollectionsBilled' => $chartCollectionsBilled,
            'commissionByPropertyFallback' => ($commissionByProperty['fallback'] ?? '') === 'collections',
            'commissionSplitFallback' => ($commissionSplit['fallback'] ?? '') === 'collections',
            'recentRequests' => $recentRequests,
            'recentPayments' => $recentPayments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildHeavyForAgent(): array
    {
        $year = (int) now()->year;
        $formulas = app(FinancialReportingFormulaService::class);
        $openInvoiceBalance = $formulas->outstandingGlobal();
        $overdueCount = PmInvoice::countPastDueOpen();
        $mtdCollected = PropertyDashboardStats::mtdCollected();
        $billedYtd = $formulas->billedForPeriod(
            Carbon::create($year, 1, 1)->startOfYear(),
            Carbon::create($year, 12, 31)->endOfYear(),
        );

        $applyAgentFilter = AgentWorkspaceScope::shouldApply();
        $agentUserId = $applyAgentFilter ? (int) Auth::id() : null;
        $landlordStats = self::landlordWorkspaceStats($applyAgentFilter, $agentUserId);

        $financialKpis = [
            [
                'label' => 'Collections (MTD)',
                'value' => PropertyMoney::kes($mtdCollected),
                'icon' => 'fa-sack-dollar',
                'route' => 'property.revenue.payments',
                'bar' => 'bg-green-600',
            ],
            [
                'label' => 'Billed (YTD)',
                'value' => PropertyMoney::kes($billedYtd),
                'icon' => 'fa-file-invoice-dollar',
                'route' => 'property.revenue.invoices',
                'bar' => 'bg-teal-500',
            ],
            [
                'label' => 'Tenant arrears',
                'value' => PropertyMoney::kes($openInvoiceBalance),
                'icon' => 'fa-scale-unbalanced',
                'route' => 'property.revenue.arrears',
                'bar' => 'bg-rose-500',
            ],
        ];

        $operationalPropertyIds = Property::query()->operational()->select('id');
        $unitsTotal = PropertyUnit::query()->whereIn('property_id', $operationalPropertyIds)->count();
        $unitsOccupied = PropertyUnit::query()->whereIn('property_id', $operationalPropertyIds)->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $occ = $unitsTotal > 0 ? round(100 * $unitsOccupied / $unitsTotal, 1) : null;
        $jobsActive = PmMaintenanceJob::query()->whereIn('status', ['quoted', 'approved', 'in_progress'])->count();
        $propertiesCount = Property::query()->operational()->count();
        $linkedProperties = (int) Property::query()->has('landlords')->count();
        $propertiesWithoutLandlord = max(0, $propertiesCount - $linkedProperties);

        $invoiceByMonth = PmInvoice::query()
            ->whereYear('issue_date', $year)
            ->selectRaw('MONTH(issue_date) as month_num, COALESCE(SUM(amount), 0) as total')
            ->groupByRaw('MONTH(issue_date)')
            ->pluck('total', 'month_num');

        $paymentByMonth = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereNotNull('paid_at')
            ->whereYear('paid_at', $year)
            ->selectRaw('MONTH(paid_at) as month_num, COALESCE(SUM(amount), 0) as total')
            ->groupByRaw('MONTH(paid_at)')
            ->pluck('total', 'month_num');

        $chartLabels = [];
        $chartInvoices = [];
        $chartPayments = [];
        for ($m = 1; $m <= 12; $m++) {
            $chartLabels[] = Carbon::createFromDate($year, $m, 1)->format('M');
            $chartInvoices[] = (float) ($invoiceByMonth[$m] ?? 0);
            $chartPayments[] = (float) ($paymentByMonth[$m] ?? 0);
        }

        $recentRequests = PmMaintenanceRequest::query()
            ->with(['unit.property'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (PmMaintenanceRequest $r) {
                $u = $r->unit;
                $unitLabel = $u && $u->property
                    ? $u->property->name.' / '.$u->label
                    : 'â€”';

                return [
                    'summary' => Str::limit($r->category.': '.$r->description, 48),
                    'unit' => $unitLabel,
                    'reported' => $r->created_at->format('Y-m-d'),
                    'status' => ucfirst(str_replace('_', ' ', $r->status)),
                    'url' => route('property.maintenance.requests'),
                ];
            })
            ->all();

        $recentPayments = PmPayment::query()
            ->with('tenant')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (PmPayment $p) {
                return [
                    'ref' => 'PAY-'.$p->id,
                    'tenant' => $p->tenant?->name ?? 'â€”',
                    'amount' => PropertyMoney::kes((float) $p->amount),
                    'channel' => $p->channel,
                    'date' => $p->paid_at?->format('Y-m-d H:i') ?? 'â€”',
                    'url' => route('property.revenue.payments'),
                ];
            })
            ->all();

        $recentUnmatched = [];
        if (Schema::hasTable('unassigned_payments')) {
            $recentUnmatched = UnassignedPayment::query()
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['transaction_id', 'amount', 'payment_method', 'reason', 'created_at'])
                ->map(function ($u) {
                    return [
                        'txn' => (string) ($u->transaction_id ?? ''),
                        'amount' => PropertyMoney::kes((float) ($u->amount ?? 0)),
                        'source' => (string) ($u->payment_method ?? ''),
                        'reason' => (string) ($u->reason ?? ''),
                        'date' => $u->created_at ? $u->created_at->format('Y-m-d H:i') : 'â€”',
                        'url' => route('property.equity.unmatched'),
                    ];
                })
                ->all();
        }

        $smsHealth = app(SmsHealthService::class);
        $today = now();
        $reminderTable = (new PmMessageLog)->getTable();
        $remindersTodayBase = PmMessageLog::query()
            ->whereDate("{$reminderTable}.created_at", $today->toDateString())
            ->whereIn("{$reminderTable}.channel", ['email', 'sms']);
        $smsHealth->applyRentReminderLogScope($remindersTodayBase, $reminderTable);
        $remindersSentToday = (clone $remindersTodayBase)
            ->whereIn("{$reminderTable}.delivery_status", ['sent', 'delivered'])
            ->count();
        $remindersFailedToday = $smsHealth->rentReminderFailuresNeedingActionToday();

        $recentReminderTable = (new PmMessageLog)->getTable();
        $recentArrearsReminders = PmMessageLog::query()
            ->whereIn("{$recentReminderTable}.channel", ['email', 'sms']);
        $smsHealth->applyRentReminderLogScope($recentArrearsReminders, $recentReminderTable);
        $recentArrearsReminders = $recentArrearsReminders
            ->orderByDesc("{$recentReminderTable}.id")
            ->limit(6)
            ->get(['channel', 'to_address', 'delivery_status', 'delivery_error', 'created_at', 'subject'])
            ->map(function (PmMessageLog $m) {
                return [
                    'when' => $m->created_at?->format('Y-m-d H:i') ?? 'â€”',
                    'channel' => strtoupper((string) ($m->channel ?? '')),
                    'to' => (string) ($m->to_address ?? ''),
                    'status' => strtoupper((string) ($m->delivery_status ?? '')),
                    'error' => (string) ($m->delivery_error ?? ''),
                    'subject' => (string) ($m->subject ?? ''),
                ];
            })
            ->all();

        $recentLeaseActivations = PmLease::query()
            ->with(['pmTenant:id,name', 'units:id,label,property_id', 'units.property:id,name'])
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereDate('start_date', '>=', now()->startOfMonth()->toDateString())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function (PmLease $l) {
                $unit = $l->units->first();
                $unitLabel = $unit && $unit->property ? ($unit->property->name.' / '.$unit->label) : 'â€”';

                return [
                    'id' => (int) $l->id,
                    'tenant' => (string) ($l->pmTenant?->name ?? ''),
                    'unit' => $unitLabel,
                    'start' => $l->start_date?->format('Y-m-d') ?? 'â€”',
                ];
            })
            ->all();

        $occupiedNoLease = PropertyUnit::query()
            ->where('status', PropertyUnit::STATUS_OCCUPIED)
            ->whereDoesntHave('leases', function ($q) {
                $q->where('status', PmLease::STATUS_ACTIVE);
            })
            ->with('property:id,name')
            ->orderBy('property_id')
            ->orderBy('label')
            ->limit(6)
            ->get(['id', 'label', 'property_id'])
            ->map(function (PropertyUnit $u) {
                return [
                    'id' => (int) $u->id,
                    'unit' => (string) $u->label,
                    'property' => (string) ($u->property?->name ?? 'â€”'),
                    'action_url' => route('property.tenants.leases', array_filter(['property_id' => $u->property_id, 'unit_id' => $u->id, 'open_create' => 1])),
                ];
            })
            ->all();

        $mailFrom = (string) (config('mail.from.address') ?? config('mail.from') ?? env('MAIL_FROM_ADDRESS', ''));
        $smtpHost = (string) (config('mail.mailers.smtp.host') ?? env('MAIL_HOST', ''));
        $mailConfigured = $mailFrom !== '' && $smtpHost !== '';
        $lastArrearsError = $smsHealth->lastUnresolvedRentReminderSmsError();
        $bulk = app(BulkSmsService::class);
        $smsWalletBalance = $bulk->walletBalanceForDisplay();
        $provider = $bulk->providerBalanceForDisplay();
        $authUser = auth()->user();
        $canManageCommunications = $authUser && $authUser->hasPmPermission('communications.manage');
        $topupConfig = $bulk->topupUiConfig();
        $defaultTopupPhone = '';
        if ($authUser) {
            foreach ([$authUser->phone ?? null, $authUser->mobile ?? null] as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    $defaultTopupPhone = $candidate;
                    break;
                }
            }
        }

        $recentLandlordLinksQuery = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->orderByDesc('pl.id')
            ->limit(6);
        if ($applyAgentFilter) {
            $recentLandlordLinksQuery->where('p.agent_user_id', $agentUserId);
        }
        $recentLandlordLinks = $recentLandlordLinksQuery
            ->get([
                'p.name as property_name',
                'u.name as landlord_name',
                'pl.ownership_percent',
            ])
            ->map(function ($row) {
                return [
                    'property' => (string) ($row->property_name ?? 'â€”'),
                    'landlord' => (string) ($row->landlord_name ?? 'â€”'),
                    'ownership' => number_format((float) ($row->ownership_percent ?? 0), 2).'%',
                ];
            })
            ->all();

        return [
            'financialKpis' => $financialKpis,
            'chartYear' => $year,
            'chartLabels' => $chartLabels,
            'chartInvoices' => $chartInvoices,
            'chartPayments' => $chartPayments,
            'recentRequests' => $recentRequests,
            'recentPayments' => $recentPayments,
            'recentLandlordLinks' => $recentLandlordLinks,
            'recentUnmatched' => $recentUnmatched,
            'recentArrearsReminders' => $recentArrearsReminders,
            'recentLeaseActivations' => $recentLeaseActivations,
            'occupiedNoLease' => $occupiedNoLease,
            'arrears7' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(7, 14)),
            'arrears14' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(14, 30)),
            'arrears30' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(30)),
            'occupancyDisplay' => $occ !== null ? $occ.'%' : 'â€”',
            'overdueCount' => $overdueCount,
            'landlords' => $landlordStats['landlord_users'],
            'linkedLandlords' => $landlordStats['linked_landlord_users'],
            'unlinkedLandlordUsers' => $landlordStats['unlinked_landlord_users'],
            'propertiesWithoutLandlord' => $propertiesWithoutLandlord,
            'jobsActive' => $jobsActive,
            'maintenanceMtd' => PropertyMoney::kes(PropertyDashboardStats::maintenanceSpendMtd()),
            'remindersSentToday' => (int) $remindersSentToday,
            'remindersFailedToday' => (int) $remindersFailedToday,
            'mailConfigured' => $mailConfigured,
            'lastArrearsError' => (string) $lastArrearsError,
            'smsWalletBalance' => (string) $smsWalletBalance,
            'smsProvider' => [
                'ok' => (bool) ($provider['ok'] ?? false),
                'balance' => isset($provider['balance']) ? (float) $provider['balance'] : null,
                'error' => (string) ($provider['error'] ?? ''),
            ],
            'smsWallet' => $bulk->walletStatusForDisplay(),
            'smsTopup' => [
                'config' => $topupConfig,
                'can_topup' => $canManageCommunications && ($topupConfig['enabled'] ?? false),
                'recent' => [],
                'default_phone' => $defaultTopupPhone,
            ],
            'canManageCommunications' => $canManageCommunications,
        ];
    }

    /**
     * Landlord counts aligned with Landlords workspace (role=landlord, agent-scoped).
     *
     * @return array{landlord_users: int, linked_landlord_users: int, unlinked_landlord_users: int}
     */
    private static function landlordWorkspaceStats(bool $applyAgentFilter, ?int $agentUserId): array
    {
        if (! Schema::hasColumn((new User)->getTable(), 'property_portal_role')) {
            return [
                'landlord_users' => 0,
                'linked_landlord_users' => 0,
                'unlinked_landlord_users' => 0,
            ];
        }

        $landlordsQuery = User::query()->where('property_portal_role', 'landlord');
        if ($applyAgentFilter && $agentUserId) {
            $landlordsQuery = LandlordWorkspaceScope::applyToLandlordUsersQuery(
                $landlordsQuery,
                User::query()->find($agentUserId),
            );
        }

        $landlordUsers = (int) (clone $landlordsQuery)->count();

        $linkedQuery = (clone $landlordsQuery)->whereHas('landlordProperties', function ($q) use ($applyAgentFilter, $agentUserId) {
            if ($applyAgentFilter && $agentUserId) {
                $q->where('properties.agent_user_id', $agentUserId);
            }
        });
        $linkedLandlordUsers = (int) $linkedQuery->count();

        return [
            'landlord_users' => $landlordUsers,
            'linked_landlord_users' => $linkedLandlordUsers,
            'unlinked_landlord_users' => max(0, $landlordUsers - $linkedLandlordUsers),
        ];
    }
}
