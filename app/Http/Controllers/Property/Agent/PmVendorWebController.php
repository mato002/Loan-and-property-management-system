<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmPortalAction;
use App\Models\PmVendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
