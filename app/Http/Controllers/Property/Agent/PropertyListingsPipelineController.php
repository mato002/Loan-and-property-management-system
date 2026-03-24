<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmListingApplication;
use App\Models\PmListingLead;
use App\Models\PropertyUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PropertyListingsPipelineController extends Controller
{
    public function leads(): View
    {
        $leads = PmListingLead::query()->with('unit.property')->orderByDesc('id')->limit(300)->get();

        $stats = [
            ['label' => 'Leads', 'value' => (string) $leads->count(), 'hint' => ''],
            ['label' => 'New', 'value' => (string) $leads->where('stage', 'new')->count(), 'hint' => ''],
            ['label' => 'Won', 'value' => (string) $leads->where('stage', 'won')->count(), 'hint' => ''],
        ];

        $rows = $leads->map(function (PmListingLead $l) {
            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-1">'.
                '<form method="POST" action="'.route('property.listings.leads.update', $l).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="stage" value="contacted" />'.
                '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Contacted</button>'.
                '</form>'.
                '<form method="POST" action="'.route('property.listings.leads.update', $l).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="stage" value="won" />'.
                '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Mark won</button>'.
                '</form>'.
                '</div>'
            );

            return [
                $l->name,
                $l->phone ?? '—',
                $l->email ?? '—',
                $l->source ?? '—',
                ucfirst($l->stage),
                $l->unit ? $l->unit->property->name.'/'.$l->unit->label : '—',
                $l->updated_at->format('Y-m-d'),
                $actions,
            ];
        })->all();

        return view('property.agent.listings.leads', [
            'stats' => $stats,
            'columns' => ['Name', 'Phone', 'Email', 'Source', 'Stage', 'Unit', 'Updated', 'Actions'],
            'tableRows' => $rows,
            'leads' => $leads,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
        ]);
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:128'],
            'stage' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'property_unit_id' => ['nullable', 'exists:property_units,id'],
        ]);

        PmListingLead::query()->create([
            ...$data,
            'stage' => $data['stage'] ?? 'new',
        ]);

        return back()->with('success', __('Lead saved.'));
    }

    public function updateLeadStage(Request $request, PmListingLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string', 'max:32'],
        ]);

        $lead->update($data);

        return back()->with('success', __('Stage updated.'));
    }

    public function applications(): View
    {
        $apps = PmListingApplication::query()->with('unit.property')->orderByDesc('id')->limit(300)->get();

        $stats = [
            ['label' => 'Applications', 'value' => (string) $apps->count(), 'hint' => ''],
            ['label' => 'In review', 'value' => (string) $apps->where('status', 'review')->count(), 'hint' => ''],
            ['label' => 'Approved', 'value' => (string) $apps->where('status', 'approved')->count(), 'hint' => ''],
        ];

        $rows = $apps->map(function (PmListingApplication $a) {
            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-1">'.
                '<form method="POST" action="'.route('property.listings.applications.update', $a).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="status" value="review" />'.
                '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Review</button>'.
                '</form>'.
                '<form method="POST" action="'.route('property.listings.applications.update', $a).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="status" value="approved" />'.
                '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Approve</button>'.
                '</form>'.
                '<form method="POST" action="'.route('property.listings.applications.update', $a).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="status" value="rejected" />'.
                '<button type="submit" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Reject</button>'.
                '</form>'.
                '</div>'
            );

            return [
                '#'.$a->id,
                $a->applicant_name,
                $a->applicant_phone ?? '—',
                $a->applicant_email ?? '—',
                $a->unit ? $a->unit->property->name.'/'.$a->unit->label : '—',
                ucfirst($a->status),
                $a->created_at->format('Y-m-d'),
                $actions,
            ];
        })->all();

        return view('property.agent.listings.applications', [
            'stats' => $stats,
            'columns' => ['#', 'Applicant', 'Phone', 'Email', 'Unit', 'Status', 'Submitted', 'Actions'],
            'tableRows' => $rows,
            'applications' => $apps,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
        ]);
    }

    public function storeApplication(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:255'],
            'applicant_phone' => ['nullable', 'string', 'max:64'],
            'applicant_email' => ['nullable', 'email', 'max:255'],
            'property_unit_id' => ['nullable', 'exists:property_units,id'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        PmListingApplication::query()->create([
            ...$data,
            'status' => $data['status'] ?? 'received',
        ]);

        return back()->with('success', __('Application recorded.'));
    }

    public function updateApplicationStatus(Request $request, PmListingApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:32'],
        ]);

        $application->update($data);

        return back()->with('success', __('Status updated.'));
    }
}
