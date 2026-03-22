<?php

namespace App\Http\Controllers\Property\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PmTenantPortalRequest;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantPortalController extends Controller
{
    public function home(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $balance = 0.0;
        $due = null;
        if ($tenant) {
            $balance = (float) PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                ->value('t');
            $due = PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->whereColumn('amount_paid', '<', 'amount')
                ->orderBy('due_date')
                ->first()?->due_date;
        }

        return view('property.tenant.home', [
            'balance' => PropertyMoney::kes($balance),
            'nextDue' => $due?->format('Y-m-d') ?? '—',
        ]);
    }

    public function lease(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $lease = $tenant
            ? PmLease::query()->where('pm_tenant_id', $tenant->id)->where('status', PmLease::STATUS_ACTIVE)->with('units.property')->first()
            : null;

        $unitLabel = $lease && $lease->units->isNotEmpty()
            ? $lease->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ')
            : '—';

        return view('property.tenant.lease', [
            'unitLabel' => $unitLabel,
            'rent' => $lease ? PropertyMoney::kes((float) $lease->monthly_rent) : '—',
            'start' => $lease?->start_date?->format('Y-m-d') ?? '—',
            'end' => $lease?->end_date?->format('Y-m-d') ?? '—',
        ]);
    }

    public function paymentsHistory(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $payments = $tenant
            ? PmPayment::query()->where('pm_tenant_id', $tenant->id)->orderByDesc('paid_at')->limit(100)->get()
            : collect();

        $rows = $payments->map(fn (PmPayment $p) => [
            $p->paid_at?->format('Y-m-d H:i') ?? '—',
            $p->channel,
            PropertyMoney::kes((float) $p->amount),
            $p->external_ref ?? '—',
            '—',
            ucfirst($p->status),
            '—',
        ])->all();

        return view('property.tenant.payments.history', [
            'stats' => [
                ['label' => 'Successful', 'value' => (string) $payments->where('status', 'completed')->count(), 'hint' => ''],
                ['label' => 'Last payment', 'value' => $payments->first()?->paid_at?->format('Y-m-d') ?? '—', 'hint' => ''],
            ],
            'columns' => ['Date', 'Channel', 'Amount', 'Reference', 'Invoice', 'Status', 'Receipt'],
            'tableRows' => $rows,
        ]);
    }

    public function receipts(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $invoices = $tenant
            ? PmInvoice::query()->where('pm_tenant_id', $tenant->id)->where('status', PmInvoice::STATUS_PAID)->orderByDesc('updated_at')->limit(50)->get()
            : collect();

        $rows = $invoices->map(fn (PmInvoice $i) => [
            $i->updated_at->format('Y-m-d'),
            'RCP-'.$i->id,
            PropertyMoney::kes((float) $i->amount),
            '—',
            $i->invoice_no,
            'Not submitted',
            '—',
        ])->all();

        return view('property.tenant.payments.receipts', [
            'stats' => [['label' => 'Receipts', 'value' => (string) $invoices->count(), 'hint' => 'Paid invoices']],
            'columns' => ['Date', 'Receipt #', 'Amount', 'Tax', 'Invoice', 'eTIMS status', 'Download'],
            'tableRows' => $rows,
        ]);
    }

    public function pay(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $balance = $tenant
            ? (float) PmInvoice::query()->where('pm_tenant_id', $tenant->id)->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')->value('t')
            : 0.0;

        return view('property.tenant.payments.pay', [
            'amountDue' => PropertyMoney::kes($balance),
        ]);
    }

    public function stkIntentStore(Request $request): RedirectResponse
    {
        $tenant = $request->user()->pmTenantProfile;
        if (! $tenant) {
            return back()->withErrors(['tenant' => 'No tenant profile is linked to your account.']);
        }

        $balance = (float) PmInvoice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
            ->value('t');

        if ($balance <= 0) {
            return back()->with('success', 'Nothing due right now — no payment request was created.');
        }

        $data = $request->validate([
            'mpesa_phone' => ['required', 'string', 'max:32'],
            'custom_amount' => ['nullable', 'string', 'max:32'],
        ]);

        $amount = $balance;
        if (! empty($data['custom_amount'])) {
            $parsed = (float) preg_replace('/[^\d.]/', '', $data['custom_amount']);
            if ($parsed > 0) {
                $amount = min($parsed, $balance);
            }
        }

        PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa_stk',
            'amount' => $amount,
            'external_ref' => null,
            'paid_at' => null,
            'status' => PmPayment::STATUS_PENDING,
            'meta' => [
                'intent' => 'stk_push',
                'phone' => $data['mpesa_phone'],
                'requested_at' => now()->toIso8601String(),
            ],
        ]);

        return back()->with(
            'success',
            'Payment request recorded for '.PropertyMoney::kes($amount).'. Complete the STK prompt on your phone when Daraja is connected; this row appears in agent payment tracking as pending.',
        );
    }

    public function maintenanceReport(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $leaseUnits = collect();
        if ($tenant) {
            $lease = PmLease::query()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmLease::STATUS_ACTIVE)
                ->with(['units.property'])
                ->first();
            if ($lease) {
                $leaseUnits = $lease->units;
            }
        }

        return view('property.tenant.maintenance.report', [
            'leaseUnits' => $leaseUnits,
        ]);
    }

    public function maintenanceReportSubmit(Request $request): RedirectResponse
    {
        $tenant = $request->user()->pmTenantProfile;
        if (! $tenant) {
            return redirect()
                ->route('property.tenant.home')
                ->withErrors(['tenant' => 'No tenant profile is linked to your account.']);
        }

        $lease = PmLease::query()
            ->where('pm_tenant_id', $tenant->id)
            ->where('status', PmLease::STATUS_ACTIVE)
            ->with('units')
            ->first();

        if (! $lease || $lease->units->isEmpty()) {
            return back()
                ->withErrors(['property_unit_id' => 'You need an active lease with a unit to submit a maintenance request.'])
                ->withInput();
        }

        $allowedIds = $lease->units->pluck('id')->all();

        $data = $request->validate([
            'property_unit_id' => ['required', 'integer', Rule::in($allowedIds)],
            'category' => ['required', 'string', 'in:plumbing,electrical,security,other'],
            'description' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'string', 'in:normal,urgent,emergency'],
            'access_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $description = $data['description'];
        if (! empty($data['access_notes'])) {
            $description .= "\n\nAccess: ".$data['access_notes'];
        }

        PmMaintenanceRequest::query()->create([
            'property_unit_id' => (int) $data['property_unit_id'],
            'reported_by_user_id' => $request->user()->id,
            'category' => $data['category'],
            'description' => $description,
            'urgency' => $data['urgency'],
            'status' => 'open',
        ]);

        return redirect()
            ->route('property.tenant.maintenance.index')
            ->with('success', 'Your maintenance request was submitted.');
    }

    public function requestsPage(Request $request): View
    {
        $list = $request->user()
            ->pmTenantPortalRequests()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('property.tenant.requests', [
            'requests' => $list,
        ]);
    }

    public function storePortalRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in([
                PmTenantPortalRequest::TYPE_VACATE,
                PmTenantPortalRequest::TYPE_EXTENSION,
            ])],
            'message' => ['nullable', 'string', 'max:5000'],
            'preferred_date' => ['nullable', 'date'],
        ]);

        PmTenantPortalRequest::query()->create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'status' => 'submitted',
            'message' => $data['message'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
        ]);

        return back()->with('success', 'Your request was submitted. Property management will follow up.');
    }

    public function explore(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $leasePropertyIds = collect();
        if ($tenant) {
            $lease = PmLease::query()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmLease::STATUS_ACTIVE)
                ->with('units.property')
                ->first();
            if ($lease) {
                $leasePropertyIds = $lease->units->pluck('property_id')->unique();
            }
        }

        $units = PropertyUnit::query()
            ->with(['property', 'publicImages'])
            ->publicListingPublished()
            ->orderByDesc('updated_at')
            ->limit(24)
            ->get();

        return view('property.tenant.explore', [
            'units' => $units,
            'leasePropertyIds' => $leasePropertyIds,
        ]);
    }
}
