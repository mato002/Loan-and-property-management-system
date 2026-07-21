<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLandlordRemittanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandlordRemittanceAgentController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $query = PmLandlordRemittanceRequest::query()
            ->with(['user'])
            ->orderByDesc('id');

        if (in_array($status, ['pending', 'acknowledged', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        return property_view('property.agent.accounting.landlord_remittances', [
            'rows' => $query->paginate(50)->withQueryString(),
            'filters' => ['status' => $status],
        ]);
    }

    public function acknowledge(Request $request, PmLandlordRemittanceRequest $remittance): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        app(LandlordPortalRemittanceService::class)->acknowledge($remittance, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Remittance instruction acknowledged.');
    }

    public function markPaid(Request $request, PmLandlordRemittanceRequest $remittance): RedirectResponse
    {
        $data = $request->validate([
            'paid_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'post_ledger' => ['nullable', 'boolean'],
        ]);

        app(LandlordPortalRemittanceService::class)->markPaid(
            $remittance,
            $request->user(),
            $data['paid_reference'] ?? null,
            $data['notes'] ?? null,
            $request->boolean('post_ledger', true),
        );

        return back()->with('success', 'Marked as paid (manual remittance recorded).');
    }

    public function cancel(Request $request, PmLandlordRemittanceRequest $remittance): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        app(LandlordPortalRemittanceService::class)->cancel($remittance, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Remittance instruction cancelled.');
    }
}
