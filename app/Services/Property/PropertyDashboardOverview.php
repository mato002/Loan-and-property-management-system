<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\PmVendor;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

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
                'label' => 'Active vendors',
                'value' => (string) $vendorsActive,
                'icon' => 'fa-truck-field',
                'route' => 'property.vendors.directory',
                'bar' => 'bg-blue-600',
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

        return [
            'kpis' => $kpis,
            'chartYear' => $year,
            'chartLabels' => $chartLabels,
            'chartInvoices' => $chartInvoices,
            'chartPayments' => $chartPayments,
            'recentRequests' => $recentRequests,
            'recentPayments' => $recentPayments,
            'arrears7' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(7, 14)),
            'arrears14' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(14, 30)),
            'arrears30' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(30)),
            'occupancyDisplay' => $occ !== null ? $occ.'%' : '—',
            'overdueCount' => $overdueCount,
            'landlords' => $landlords,
            'jobsActive' => $jobsActive,
            'maintenanceMtd' => PropertyMoney::kes(PropertyDashboardStats::maintenanceSpendMtd()),
        ];
    }
}
