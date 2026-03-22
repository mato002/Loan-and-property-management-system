<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmMaintenanceJob;
use App\Models\PmPortalAction;
use App\Models\PmVendor;
use App\Services\Property\PropertyChartSeries;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PmVendorWebController extends Controller
{
    public function directory(): View
    {
        $vendors = PmVendor::query()->orderBy('name')->get();

        $stats = [
            ['label' => 'Vendors', 'value' => (string) $vendors->count(), 'hint' => ''],
            ['label' => 'Active', 'value' => (string) $vendors->where('status', 'active')->count(), 'hint' => ''],
            ['label' => 'Inactive', 'value' => (string) $vendors->where('status', '!=', 'active')->count(), 'hint' => ''],
        ];

        $rows = $vendors->map(fn (PmVendor $v) => [
            $v->name,
            $v->category ?? '—',
            $v->phone ?? '—',
            $v->email ?? '—',
            '—',
            $v->rating !== null ? number_format((float) $v->rating, 1) : '—',
            ucfirst($v->status),
        ])->all();

        return view('property.agent.vendors.directory', [
            'stats' => $stats,
            'columns' => ['Vendor', 'Category', 'Contact', 'Payment terms', 'Insurance until', 'Rating', 'Status'],
            'tableRows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
        ]);

        PmVendor::query()->create($data);

        return back()->with('success', 'Vendor saved.');
    }

    public function createBiddingRfqForm(): View
    {
        return view('property.agent.vendors.bidding_create');
    }

    public function storeBiddingRfq(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date'],
            'access_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $ctx = array_filter([
            'property_unit' => $data['property_unit'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'access_notes' => $data['access_notes'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        PmPortalAction::query()->create([
            'user_id' => $request->user()->id,
            'portal_role' => 'agent',
            'action_key' => 'create_vendor_rfq',
            'notes' => $data['scope'],
            'context' => $ctx !== [] ? $ctx : null,
        ]);

        return redirect()
            ->route('property.vendors.bidding')
            ->with('success', 'RFQ draft saved. Vendors can be invited from your workflow when RFQs are wired to the database.');
    }

    public function bidding(): View
    {
        $rfqs = PmPortalAction::query()
            ->where('action_key', 'create_vendor_rfq')
            ->with('user')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $stats = [
            ['label' => 'RFQ drafts', 'value' => (string) $rfqs->count(), 'hint' => 'Portal actions'],
            ['label' => 'MTD', 'value' => (string) $rfqs->where('created_at', '>=', now()->startOfMonth())->count(), 'hint' => ''],
        ];

        $rows = $rfqs->map(fn (PmPortalAction $a) => [
            '#'.$a->id,
            $a->context['property_unit'] ?? '—',
            Str::limit($a->notes, 48),
            ($a->context['deadline'] ?? '—'),
            '—',
            '—',
            'Draft',
        ])->all();

        return view('property.agent.vendors.bidding', [
            'stats' => $stats,
            'columns' => ['RFQ #', 'Property / unit', 'Scope', 'Deadline', 'Invited', 'Quotes', 'Status'],
            'tableRows' => $rows,
        ]);
    }

    public function quotes(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property', 'vendor'])
            ->where('status', 'quoted')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $stats = [
            ['label' => 'Quoted jobs', 'value' => (string) $jobs->count(), 'hint' => ''],
            ['label' => 'With vendor', 'value' => (string) $jobs->filter(fn ($j) => $j->pm_vendor_id)->count(), 'hint' => ''],
        ];

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            '#'.$j->id,
            $j->vendor?->name ?? '—',
            $j->request->unit->property->name.'/'.$j->request->unit->label,
            $j->quote_amount !== null ? PropertyMoney::kes((float) $j->quote_amount) : '—',
            $j->created_at->format('Y-m-d'),
            ucfirst(str_replace('_', ' ', $j->status)),
            '—',
            '—',
        ])->all();

        return view('property.agent.vendors.quotes', [
            'stats' => $stats,
            'columns' => ['Job', 'Vendor', 'Unit', 'Quote', 'Date', 'Status', 'Lead time', 'Select'],
            'tableRows' => $rows,
        ]);
    }

    public function performance(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with('vendor')
            ->whereNotNull('pm_vendor_id')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $byVendor = $jobs->groupBy('pm_vendor_id');
        $rows = $byVendor->map(function ($group) {
            /** @var Collection<int, PmMaintenanceJob> $group */
            $v = $group->first()->vendor;
            $done = $group->where('status', 'done')->count();
            $sum = (float) $group->sum(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0));

            return [
                $v?->name ?? '—',
                (string) $group->count(),
                (string) $done,
                PropertyMoney::kes($sum),
                $v?->rating !== null ? number_format((float) $v->rating, 1) : '—',
                '—',
            ];
        })->values()->all();

        $top = $rows[0][0] ?? '—';
        $doneRate = $jobs->isNotEmpty()
            ? round(100 * $jobs->where('status', 'done')->count() / $jobs->count(), 1)
            : null;

        return view('property.agent.vendors.performance', [
            'stats' => [
                ['label' => 'Vendors used', 'value' => (string) count($rows), 'hint' => 'With jobs'],
                ['label' => 'Jobs sampled', 'value' => (string) $jobs->count(), 'hint' => ''],
                ['label' => 'Top vendor', 'value' => $top, 'hint' => 'By job count'],
                ['label' => 'Done / all', 'value' => $doneRate !== null ? $doneRate.'%' : '—', 'hint' => 'Completion share'],
            ],
            'columns' => ['Vendor', 'Jobs', 'Completed', 'Quoted total', 'Rating', 'SLA'],
            'tableRows' => $rows,
            'scatterPoints' => PropertyChartSeries::vendorScatterPoints(),
        ]);
    }

    public function workRecords(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property', 'vendor'])
            ->where('status', 'done')
            ->orderByDesc('completed_at')
            ->limit(200)
            ->get();

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            $j->completed_at?->format('Y-m-d') ?? '—',
            '#'.$j->id,
            $j->vendor?->name ?? '—',
            $j->request->unit->property->name.'/'.$j->request->unit->label,
            $j->quote_amount !== null ? PropertyMoney::kes((float) $j->quote_amount) : '—',
            Str::limit((string) $j->notes, 40),
        ])->all();

        return view('property.agent.vendors.work_records', [
            'stats' => [
                ['label' => 'Records', 'value' => (string) $jobs->count(), 'hint' => 'Completed'],
            ],
            'columns' => ['Date', 'Job', 'Vendor', 'Unit', 'Amount', 'Notes'],
            'tableRows' => $rows,
        ]);
    }
}
