<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\PmPortalAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PropertyPortalQuickActionController extends Controller
{
    public function storeAgent(Request $request): RedirectResponse
    {
        return $this->store($request, 'agent');
    }

    public function storeLandlord(Request $request): RedirectResponse
    {
        return $this->store($request, 'landlord');
    }

    public function storeTenant(Request $request): RedirectResponse
    {
        return $this->store($request, 'tenant');
    }

    protected function store(Request $request, string $portalRole): RedirectResponse
    {
        $user = $request->user();
        $actualRole = $user->property_portal_role ?? 'agent';

        if ($actualRole !== $portalRole) {
            abort(403);
        }

        $data = $request->validate([
            'action_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'context' => ['nullable', 'array'],
            'context.*' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'max:12288', 'mimes:csv,txt'],
        ]);

        $ctx = is_array($data['context'] ?? null) ? $data['context'] : [];
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('pm-bank-imports/'.now()->format('Y/m'), 'local');
            $ctx['import_file'] = $path;
        }

        PmPortalAction::query()->create([
            'user_id' => $user->id,
            'portal_role' => $portalRole,
            'action_key' => $data['action_key'],
            'notes' => $data['notes'] ?? null,
            'context' => $ctx !== [] ? $ctx : null,
        ]);

        $readable = Str::headline(str_replace('_', ' ', $data['action_key']));

        return back()->with('success', 'Recorded: '.$readable.'.');
    }
}
