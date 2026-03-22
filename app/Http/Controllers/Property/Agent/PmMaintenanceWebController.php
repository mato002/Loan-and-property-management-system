<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmVendor;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PmMaintenanceWebController extends Controller
{
    public function requests(): View
    {
        $requests = PmMaintenanceRequest::query()->with(['unit.property', 'reportedBy'])->orderByDesc('id')->limit(200)->get();

        $stats = [
            ['label' => 'Open', 'value' => (string) $requests->where('status', 'open')->count(), 'hint' => ''],
            ['label' => 'In progress', 'value' => (string) $requests->where('status', 'in_progress')->count(), 'hint' => ''],
            ['label' => 'Done', 'value' => (string) $requests->where('status', 'done')->count(), 'hint' => ''],
            ['label' => 'Total', 'value' => (string) $requests->count(), 'hint' => 'Listed'],
        ];

        $rows = $requests->map(fn (PmMaintenanceRequest $r) => [
            '#'.$r->id,
            $r->unit->property->name.'/'.$r->unit->label,
            $r->category,
            Str::limit($r->description, 40),
            $r->created_at->format('Y-m-d'),
            ucfirst($r->urgency),
            ucfirst(str_replace('_', ' ', $r->status)),
            $r->reportedBy?->name ?? '—',
        ])->all();

        return view('property.agent.maintenance.requests', [
            'stats' => $stats,
            'columns' => ['ID', 'Unit', 'Category', 'Summary', 'Reported', 'Priority', 'Status', 'Assignee'],
            'tableRows' => $rows,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
        ]);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'category' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:normal,urgent,emergency'],
        ]);

        PmMaintenanceRequest::query()->create([
            ...$data,
            'reported_by_user_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return back()->with('success', 'Maintenance request logged.');
    }

    public function jobs(): View
    {
        $jobs = PmMaintenanceJob::query()->with(['request.unit.property', 'vendor'])->orderByDesc('id')->limit(200)->get();

        $mtdCompletedCount = PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->whereYear('completed_at', now()->year)
            ->whereMonth('completed_at', now()->month)
            ->count();
        $mtdSpend = (float) PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->whereYear('completed_at', now()->year)
            ->whereMonth('completed_at', now()->month)
            ->sum('quote_amount');

        $stats = [
            ['label' => 'Jobs', 'value' => (string) $jobs->count(), 'hint' => 'Listed'],
            ['label' => 'Completed (MTD)', 'value' => (string) $mtdCompletedCount, 'hint' => ''],
            ['label' => 'Spend (MTD)', 'value' => PropertyMoney::kes($mtdSpend), 'hint' => 'Quoted'],
        ];

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            '#'.$j->id,
            $j->request->unit->property->name.'/'.$j->request->unit->label,
            $j->vendor?->name ?? '—',
            $j->quote_amount !== null ? number_format((float) $j->quote_amount, 2) : '—',
            '—',
            '—',
            ucfirst(str_replace('_', ' ', $j->status)),
            '—',
        ])->all();

        return view('property.agent.maintenance.jobs', [
            'stats' => $stats,
            'columns' => ['Job #', 'Unit', 'Vendor', 'Quote', 'Approved', 'Schedule', 'Status', 'Actions'],
            'tableRows' => $rows,
            'requests' => PmMaintenanceRequest::query()->with('unit.property')->orderByDesc('id')->limit(100)->get(),
            'vendors' => PmVendor::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_maintenance_request_id' => ['required', 'exists:pm_maintenance_requests,id'],
            'pm_vendor_id' => ['nullable', 'exists:pm_vendors,id'],
            'quote_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:quoted,approved,in_progress,done,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $completedAt = $data['status'] === 'done' ? now() : null;

        PmMaintenanceJob::query()->create([
            ...$data,
            'completed_at' => $completedAt,
        ]);

        return back()->with('success', 'Job saved.');
    }

    public function history(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property', 'vendor'])
            ->where(function ($q) {
                $q->where('status', 'done')->orWhere('status', 'cancelled');
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $stats = [
            ['label' => 'Closed jobs', 'value' => (string) $jobs->count(), 'hint' => 'Listed'],
            ['label' => 'Done', 'value' => (string) $jobs->where('status', 'done')->count(), 'hint' => ''],
            ['label' => 'Cancelled', 'value' => (string) $jobs->where('status', 'cancelled')->count(), 'hint' => ''],
        ];

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            $j->completed_at?->format('Y-m-d') ?? '—',
            '#'.$j->id,
            $j->request->unit->property->name.'/'.$j->request->unit->label,
            $j->vendor?->name ?? '—',
            $j->quote_amount !== null ? number_format((float) $j->quote_amount, 2) : '—',
            ucfirst(str_replace('_', ' ', $j->status)),
            Str::limit((string) $j->notes, 36),
        ])->all();

        return view('property.agent.maintenance.history', [
            'stats' => $stats,
            'columns' => ['Completed', 'Job #', 'Unit', 'Vendor', 'Quote', 'Status', 'Notes'],
            'tableRows' => $rows,
        ]);
    }

    public function costs(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property'])
            ->whereNotNull('quote_amount')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $ytd = $jobs->filter(fn (PmMaintenanceJob $j) => $j->completed_at
            && (int) $j->completed_at->year === (int) now()->year);
        $ytdSum = (float) $ytd->sum('quote_amount');

        $byCategory = $jobs->groupBy(fn (PmMaintenanceJob $j) => $j->request->category ?: 'General');

        $rows = $byCategory->map(function ($group, $category) {
            /** @var Collection<int, PmMaintenanceJob> $group */
            $sum = (float) $group->sum(fn (PmMaintenanceJob $j) => (float) $j->quote_amount);
            $done = $group->where('status', 'done')->count();

            return [
                (string) $category,
                'All properties',
                now()->format('Y'),
                PropertyMoney::kes($sum),
                (string) $group->count(),
                '—',
                $done.' completed',
            ];
        })->values()->all();

        $unitCount = PropertyUnit::query()->count();
        $costPerDoor = $unitCount > 0 ? $ytdSum / $unitCount : 0.0;
        $topCategory = '—';
        if ($byCategory->isNotEmpty()) {
            $topCategory = (string) $byCategory->sortByDesc(fn ($g) => $g->sum(fn (PmMaintenanceJob $j) => (float) $j->quote_amount))->keys()->first();
        }

        return view('property.agent.maintenance.costs', [
            'stats' => [
                ['label' => 'Spend (YTD)', 'value' => PropertyMoney::kes($ytdSum), 'hint' => 'Completed jobs, quoted'],
                ['label' => 'Cost / door (YTD)', 'value' => PropertyMoney::kes($costPerDoor), 'hint' => 'Avg across '.$unitCount.' units'],
                ['label' => 'Top cost driver', 'value' => (string) $topCategory, 'hint' => 'By request category'],
                ['label' => 'Jobs with quotes', 'value' => (string) $jobs->count(), 'hint' => 'Listed sample'],
            ],
            'columns' => ['Scope', 'Property / unit', 'Period', 'Spend', 'Tickets', 'vs budget', 'Notes'],
            'tableRows' => $rows,
        ]);
    }

    public function frequency(): View
    {
        $requests = PmMaintenanceRequest::query()
            ->where('created_at', '>=', now()->subMonths(12))
            ->orderBy('created_at')
            ->get();

        $byMonth = $requests->groupBy(fn (PmMaintenanceRequest $r) => $r->created_at->format('Y-m'));
        $rows = $byMonth->map(function ($group, $ym) {
            $byCat = $group->groupBy(fn (PmMaintenanceRequest $r) => $r->category ?: 'General');

            return [
                (string) $ym,
                (string) $group->count(),
                (string) $byCat->count(),
                (string) $group->where('urgency', 'emergency')->count(),
                '—',
                '—',
            ];
        })->sortKeysDesc()->values()->all();

        $unitCount = PropertyUnit::query()->count();
        $monthsWithData = $byMonth->count();
        $emergencyTotal = $requests->where('urgency', 'emergency')->count();

        return view('property.agent.maintenance.frequency', [
            'stats' => [
                ['label' => 'Requests (12 mo)', 'value' => (string) $requests->count(), 'hint' => ''],
                ['label' => 'Months with data', 'value' => (string) $monthsWithData, 'hint' => ''],
                ['label' => 'Emergency (12 mo)', 'value' => (string) $emergencyTotal, 'hint' => ''],
                ['label' => 'Units in portfolio', 'value' => (string) $unitCount, 'hint' => ''],
            ],
            'columns' => ['Month', 'Tickets', 'Categories touched', 'Emergency', 'Repeat units', 'Notes'],
            'tableRows' => $rows,
        ]);
    }
}
