<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLandlordRemittanceRequest;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandlordRemittanceAgentController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $query = PmLandlordRemittanceRequest::query()
            ->with(['user'])
            ->orderByDesc('id');

        if (in_array($status, ['pending', 'acknowledged', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q): void {
                $w->where('destination_detail', 'like', '%'.$q.'%')
                    ->orWhere('paid_reference', 'like', '%'.$q.'%')
                    ->orWhere('reference_note', 'like', '%'.$q.'%')
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%'));
            });
        }

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, TabularExport::TABLE_FORMATS, true)) {
            $rows = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'landlord-remittances-'.now()->format('Ymd_His'),
                ['ID', 'Landlord', 'Amount', 'Destination', 'Detail', 'Status', 'Paid reference', 'Requested'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield [
                            (string) $row->id,
                            (string) ($row->user?->name ?? ''),
                            number_format((float) $row->amount, 2, '.', ''),
                            (string) $row->destination,
                            (string) ($row->destination_detail ?? ''),
                            $row->statusLabel(),
                            (string) ($row->paid_reference ?? ''),
                            $row->created_at?->format('Y-m-d') ?? '',
                        ];
                    }
                },
                $export,
                [
                    'title' => 'Landlord remittance instructions',
                    'subtitle' => $rows->count().' instruction'.($rows->count() === 1 ? '' : 's'),
                ],
            );
        }

        return property_view('property.agent.accounting.landlord_remittances', [
            'rows' => $query->paginate(50)->withQueryString(),
            'filters' => ['status' => $status, 'q' => $q],
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
