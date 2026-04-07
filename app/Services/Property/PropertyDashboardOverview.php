<?php

namespace App\Services\Property;

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
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\BulkSmsService;

final class PropertyDashboardOverview
{
    /**
     * @return array{
     *   kpis: list<array{label: string, value: string, icon: string, route: string, bar: string}>,
     *   chartYear: int,
     *   chartLabels: list<string>,
     *   chartInvoices: list<float>,
     *   chartPayments: list<float>,
     *   recentRequests: list<array{summary: string, unit: string, reported: string, status: string, url: string}>,
     *   recentPayments: list<array{ref: string, tenant: string, amount: string, channel: string, date: string, url: string}>,
     *   arrears7: string,
     *   arrears14: string,
     *   arrears30: string,
     *   occupancyDisplay: string,
     * }
     */
    public static function forAgent(): array
    {
        $year = (int) now()->year;

        $properties = Property::query()->count();
        $unitsTotal = PropertyUnit::query()->count();
        $unitsOccupied = PropertyUnit::query()->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $unitsVacant = PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->count();
        $tenants = PmTenant::query()->count();
        $leasesActive = PmLease::query()->where('status', PmLease::STATUS_ACTIVE)->count();
        $leasesExpiring = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->count();

        $openInvoiceBalance = (float) (PmInvoice::query()
            ->whereColumn('amount_paid', '<', 'amount')
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as t')
            ->value('t') ?? 0);

        $overdueCount = PmInvoice::query()->where('status', PmInvoice::STATUS_OVERDUE)->count();
        $mtdCollected = PropertyDashboardStats::mtdCollected();
        $billedYtd = (float) PmInvoice::query()->whereYear('issue_date', $year)->sum('amount');

        $maintOpen = PmMaintenanceRequest::query()->where('status', 'open')->count();
        $maintInProgress = PmMaintenanceRequest::query()->where('status', 'in_progress')->count();
        $jobsActive = PmMaintenanceJob::query()->whereIn('status', ['quoted', 'approved', 'in_progress'])->count();
        $vendorsActive = PmVendor::query()->where('status', 'active')->count();
        $landlords = User::query()->where('property_portal_role', 'landlord')->count();
        $linkedLandlords = (int) DB::table('property_landlord')->distinct('user_id')->count('user_id');
        $linkedProperties = (int) Property::query()->has('landlords')->count();
        $propertiesWithoutLandlord = max(0, $properties - $linkedProperties);
        $unmatchedBankPayments = Schema::hasTable('unassigned_payments')
            ? (int) DB::table('unassigned_payments')->count()
            : 0;

        $occ = PropertyDashboardStats::occupancyRate();

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
                'label' => 'Collected (MTD)',
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
                'label' => 'Outstanding AR',
                'value' => PropertyMoney::kes($openInvoiceBalance),
                'icon' => 'fa-scale-unbalanced',
                'route' => 'property.revenue.arrears',
                'bar' => 'bg-rose-500',
            ],
            [
                'label' => 'Open maintenance',
                'value' => (string) ($maintOpen + $maintInProgress),
                'icon' => 'fa-screwdriver-wrench',
                'route' => 'property.maintenance.requests',
                'bar' => 'bg-slate-500',
            ],
            [
                'label' => 'Linked landlords',
                'value' => (string) $linkedLandlords,
                'icon' => 'fa-user-check',
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

        $chartLabels = [];
        $chartInvoices = [];
        $chartPayments = [];
        for ($m = 1; $m <= 12; $m++) {
            $chartLabels[] = Carbon::createFromDate($year, $m, 1)->format('M');
            $chartInvoices[] = (float) PmInvoice::query()
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $m)
                ->sum('amount');
            $chartPayments[] = (float) PmPayment::query()
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->whereNotNull('paid_at')
                ->whereYear('paid_at', $year)
                ->whereMonth('paid_at', $m)
                ->sum('amount');
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
                    'tenant' => $p->tenant?->name ?? '—',
                    'amount' => PropertyMoney::kes((float) $p->amount),
                    'channel' => $p->channel,
                    'date' => $p->paid_at?->format('Y-m-d H:i') ?? '—',
                    'url' => route('property.revenue.payments'),
                ];
            })
            ->all();

        $recentUnmatched = [];
        if (Schema::hasTable('unassigned_payments')) {
            $recentUnmatched = \App\Models\UnassignedPayment::query()
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['transaction_id', 'amount', 'payment_method', 'reason', 'created_at'])
                ->map(function ($u) {
                    return [
                        'txn' => (string) ($u->transaction_id ?? ''),
                        'amount' => PropertyMoney::kes((float) ($u->amount ?? 0)),
                        'source' => (string) ($u->payment_method ?? ''),
                        'reason' => (string) ($u->reason ?? ''),
                        'date' => $u->created_at ? $u->created_at->format('Y-m-d H:i') : '—',
                        'url' => route('property.equity.unmatched'),
                    ];
                })
                ->all();
        }

        $arrearsToday = PmMessageLog::query()
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('channel', ['email', 'sms'])
            ->where('subject', 'like', '[ARREARS]%');
        $remindersSentToday = (clone $arrearsToday)->where('delivery_status', 'sent')->count();
        $remindersFailedToday = (clone $arrearsToday)->where('delivery_status', 'failed')->count();

        $recentArrearsReminders = PmMessageLog::query()
            ->whereIn('channel', ['email', 'sms'])
            ->where('subject', 'like', '[ARREARS]%')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['channel', 'to_address', 'delivery_status', 'delivery_error', 'created_at', 'subject'])
            ->map(function (PmMessageLog $m) {
                return [
                    'when' => $m->created_at?->format('Y-m-d H:i') ?? '—',
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
                $unitLabel = $unit && $unit->property ? ($unit->property->name.' / '.$unit->label) : '—';
                return [
                    'id' => (int) $l->id,
                    'tenant' => (string) ($l->pmTenant?->name ?? ''),
                    'unit' => $unitLabel,
                    'start' => $l->start_date?->format('Y-m-d') ?? '—',
                ];
            })
            ->all();

        // Takeover checklist: occupied units with no active lease
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
                    'property' => (string) ($u->property?->name ?? '—'),
                    'action_url' => route('property.tenants.leases', ['property_id' => $u->property_id]),
                ];
            })
            ->all();

        // System health
        $mailFrom = (string) (config('mail.from.address') ?? config('mail.from') ?? env('MAIL_FROM_ADDRESS', ''));
        $smtpHost = (string) (config('mail.mailers.smtp.host') ?? env('MAIL_HOST', ''));
        $mailConfigured = $mailFrom !== '' && $smtpHost !== '';
        $lastArrearsError = PmMessageLog::query()
            ->where('delivery_status', 'failed')
            ->where('subject', 'like', '[ARREARS]%')
            ->orderByDesc('id')
            ->value('delivery_error') ?? '';
        $bulk = app(BulkSmsService::class);
        $smsWalletBalance = $bulk->walletBalance();
        $provider = $bulk->providerBalance();

        $recentLandlordLinks = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->orderByDesc('pl.id')
            ->limit(6)
            ->get([
                'p.name as property_name',
                'u.name as landlord_name',
                'pl.ownership_percent',
            ])
            ->map(function ($row) {
                return [
                    'property' => (string) ($row->property_name ?? '—'),
                    'landlord' => (string) ($row->landlord_name ?? '—'),
                    'ownership' => number_format((float) ($row->ownership_percent ?? 0), 2).'%',
                ];
            })
            ->all();

        return [
            'kpis' => $kpis,
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
            'occupancyDisplay' => $occ !== null ? $occ.'%' : '—',
            'overdueCount' => $overdueCount,
            'landlords' => $landlords,
            'linkedLandlords' => $linkedLandlords,
            'linkedProperties' => $linkedProperties,
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
        ];
    }
}
