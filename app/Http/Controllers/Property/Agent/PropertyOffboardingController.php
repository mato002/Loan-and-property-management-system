<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLease;
use App\Models\Property;
use App\Services\Property\PropertyOffboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyOffboardingController extends Controller
{
    public function show(Request $request, Property $property, PropertyOffboardingService $offboarding): View
    {
        $step = max(1, min(5, (int) $request->query('step', 1)));
        $loads = ['landlords' => fn ($q) => $q->orderBy('name')];
        if (Schema::hasColumn('properties', 'archived_by')) {
            $loads['archivedByUser'] = fn ($q) => $q->select('id', 'name');
        }
        $property->load($loads);

        $check = $offboarding->statusCheck($property);
        $canDetach = $offboarding->canDetachLandlord(
            $property,
            $request->user()?->hasPmPermission('property.archive.override') ?? false
        );
        $canArchive = $offboarding->canArchive(
            $property,
            $request->user()?->hasPmPermission('property.archive.override') ?? false
        );

        return property_view('property.agent.properties.offboarding', [
            'property' => $property,
            'step' => $step,
            'check' => $check,
            'canDetach' => $canDetach,
            'canArchive' => $canArchive,
        ]);
    }

    public function start(Request $request, Property $property, PropertyOffboardingService $offboarding): RedirectResponse
    {
        if (! $request->user()?->hasPmPermission('properties.manage') && ! $request->user()?->hasPmPermission('property.offboarding.start')) {
            abort(403);
        }

        $data = $request->validate([
            'management_end_reason' => ['required', 'string', 'max:255'],
            'offboarding_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $offboarding->startOffboarding(
            $property,
            $data['management_end_reason'],
            $data['offboarding_notes'] ?? null,
        );

        return redirect()
            ->route('property.properties.offboarding', ['property' => $property->id, 'step' => 1])
            ->with('success', 'Offboarding started for '.$property->name.'.');
    }

    public function updateNotes(Request $request, Property $property, PropertyOffboardingService $offboarding): RedirectResponse
    {
        if (! $request->user()?->hasPmPermission('property.offboarding.start')) {
            abort(403);
        }

        $data = $request->validate([
            'management_end_reason' => ['nullable', 'string', 'max:255'],
            'offboarding_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $property->update(array_filter([
            'management_end_reason' => $data['management_end_reason'] ?? null,
            'offboarding_notes' => $data['offboarding_notes'] ?? null,
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Offboarding notes updated.');
    }

    public function archive(Request $request, Property $property, PropertyOffboardingService $offboarding): RedirectResponse
    {
        if (! $request->user()?->hasPmPermission('property.offboarding.complete')) {
            abort(403);
        }

        $data = $request->validate([
            'management_end_reason' => ['nullable', 'string', 'max:255'],
            'admin_override' => ['sometimes', 'boolean'],
            'final_status' => ['nullable', 'in:archived,ended_management'],
        ]);

        $override = (bool) ($data['admin_override'] ?? false)
            && ($request->user()?->hasPmPermission('property.archive.override') ?? false);

        $finalStatus = (string) ($data['final_status'] ?? Property::MANAGEMENT_ARCHIVED);

        if ($finalStatus === Property::MANAGEMENT_ENDED) {
            $offboarding->endManagement($property, $data['management_end_reason'] ?? null, $override);
            $message = 'Management ended for '.$property->name.'. Historical records are preserved.';
        } else {
            $offboarding->archive($property, $data['management_end_reason'] ?? null, $override);
            $message = 'Property archived. It is hidden from operational dashboards but history remains available.';
        }

        return redirect()
            ->route('property.properties.show', $property)
            ->with('success', $message);
    }

    public function restore(Request $request, Property $property, PropertyOffboardingService $offboarding): RedirectResponse
    {
        if (! $request->user()?->hasPmPermission('property.archive.restore')) {
            abort(403);
        }

        $offboarding->restore($property);

        return redirect()
            ->route('property.properties.show', $property)
            ->with('success', 'Property restored to active management.');
    }

    public function exportHandover(Property $property, PropertyOffboardingService $offboarding): StreamedResponse
    {
        if (! Auth::user()?->hasPmPermission('property.archive.view')) {
            abort(403);
        }

        return $offboarding->handoverCsvResponse($property);
    }

    public function scheduleLeaseTermination(Request $request, Property $property, PmLease $lease): RedirectResponse
    {
        if (! $request->user()?->hasPmPermission('property.offboarding.start')) {
            abort(403);
        }

        $data = $request->validate([
            'end_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $lease->loadMissing('units');
        $belongs = $lease->units->contains(fn ($u) => (int) $u->property_id === (int) $property->id);
        if (! $belongs) {
            abort(404);
        }

        $lease->update(['end_date' => $data['end_date']]);

        return back()->with('success', 'Lease #'.$lease->id.' end date scheduled.');
    }
}
