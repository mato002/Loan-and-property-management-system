<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\SmsLog;
use App\Models\SmsSchedule;
use App\Models\SmsTemplate;
use App\Models\SmsWalletTopup;
use App\Services\BulkSmsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoanBulkSmsController extends Controller
{
    public function compose(Request $request, BulkSmsService $bulkSms): View
    {
        $templates = SmsTemplate::query()->orderBy('name')->get();
        $prefillBody = null;
        $prefillTemplateId = null;
        if ($request->filled('template')) {
            $t = SmsTemplate::query()->find($request->integer('template'));
            if ($t) {
                $prefillBody = $t->body;
                $prefillTemplateId = $t->id;
            }
        }

        $activeTenantRows = DB::table('pm_tenants as t')
            ->join('pm_leases as l', 'l.pm_tenant_id', '=', 't.id')
            ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
            ->join('property_units as u', 'u.id', '=', 'lu.property_unit_id')
            ->join('properties as p', 'p.id', '=', 'u.property_id')
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->whereNotNull('t.phone')
            ->where('t.phone', '!=', '')
            ->selectRaw('t.id as tenant_id, t.name as tenant_name, t.phone as tenant_phone, p.id as property_id, p.name as property_name, u.label as unit_label')
            ->orderBy('p.name')
            ->orderBy('t.name')
            ->get();

        $propertyOptions = Property::query()
            ->whereIn('id', $activeTenantRows->pluck('property_id')->filter()->unique()->values()->all())
            ->orderBy('name')
            ->get(['id', 'name']);

        $tenantOptions = $activeTenantRows
            ->groupBy('tenant_id')
            ->map(function ($rows, $tenantId) {
                $first = $rows->first();
                $propertyIds = $rows->pluck('property_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
                $unitLabels = $rows->pluck('unit_label')->filter()->unique()->values()->all();

                return [
                    'id' => (int) $tenantId,
                    'name' => (string) ($first->tenant_name ?? 'Tenant'),
                    'phone' => (string) ($first->tenant_phone ?? ''),
                    'property_ids' => $propertyIds,
                    'property_names' => $rows->pluck('property_name')->filter()->unique()->values()->all(),
                    'units' => $unitLabels,
                ];
            })
            ->values();

        return view('loan.bulksms.compose', [
            'templates' => $templates,
            'walletBalance' => $bulkSms->walletBalance(),
            'currency' => $bulkSms->currency(),
            'costPerSms' => $bulkSms->costPerSms(),
            'prefillBody' => $prefillBody,
            'prefillTemplateId' => $prefillTemplateId,
            'propertyOptions' => $propertyOptions,
            'tenantOptions' => $tenantOptions,
        ]);
    }

    public function composeStore(Request $request, BulkSmsService $bulkSms): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_source' => ['nullable', 'in:manual,all_tenants,property_tenants'],
            'recipients' => ['nullable', 'string'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'tenant_selection_mode' => ['nullable', 'in:all,selected'],
            'tenant_ids' => ['nullable', 'array'],
            'tenant_ids.*' => ['integer', 'exists:pm_tenants,id'],
            'message' => ['required', 'string', 'max:1000'],
            'schedule_at' => ['nullable', 'date', 'after:now'],
            'sms_template_id' => ['nullable', 'exists:sms_templates,id'],
        ]);

        $recipientSource = (string) ($validated['recipient_source'] ?? 'manual');
        $phones = [];
        if ($recipientSource === 'all_tenants') {
            $phones = PmTenant::query()
                ->whereHas('leases', fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE))
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->pluck('phone')
                ->map(fn ($p) => (string) $p)
                ->all();
        } elseif ($recipientSource === 'property_tenants') {
            $propertyId = (int) ($validated['property_id'] ?? 0);
            if ($propertyId <= 0) {
                return back()->withInput()->withErrors(['property_id' => 'Select a property for property tenants.']);
            }

            $tenantSelectionMode = (string) ($validated['tenant_selection_mode'] ?? 'all');
            $baseTenantQuery = PmTenant::query()
                ->whereHas('leases', function ($q) use ($propertyId) {
                    $q->where('status', PmLease::STATUS_ACTIVE)
                        ->whereHas('units', fn ($uq) => $uq->where('property_id', $propertyId));
                })
                ->whereNotNull('phone')
                ->where('phone', '!=', '');

            if ($tenantSelectionMode === 'selected') {
                $tenantIds = collect((array) ($validated['tenant_ids'] ?? []))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                if ($tenantIds === []) {
                    return back()->withInput()->withErrors(['tenant_ids' => 'Select at least one tenant for the chosen property.']);
                }
                $baseTenantQuery->whereIn('id', $tenantIds);
            }

            $phones = $baseTenantQuery->pluck('phone')->map(fn ($p) => (string) $p)->all();
        } else {
            $rawRecipients = (string) ($validated['recipients'] ?? '');
            if (trim($rawRecipients) === '') {
                return back()
                    ->withInput()
                    ->withErrors(['recipients' => 'Enter at least one phone number or choose a tenant source.']);
            }
            $phones = $bulkSms->normalizeRecipientList($rawRecipients);
        }

        if ($recipientSource !== 'manual') {
            $phones = $bulkSms->normalizeRecipientList(implode("\n", $phones));
        }
        $userId = $request->user()?->id;

        if ($phones === []) {
            return back()
                ->withInput()
                ->withErrors(['recipients' => 'No valid recipient phone numbers found for the selected target.']);
        }

        if (! empty($validated['schedule_at'])) {
            $when = Carbon::parse($validated['schedule_at']);
            $bulkSms->createSchedule(
                $validated['message'],
                $phones,
                $when,
                $validated['sms_template_id'] ?? null,
                $userId
            );

            return redirect()
                ->route('loan.bulksms.schedules')
                ->with('status', 'SMS queued for '.$when->format('Y-m-d H:i').'.');
        }

        $result = $bulkSms->sendNow($validated['message'], $phones, $userId, null);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['message' => $result['error'] ?? 'Could not send messages.']);
        }

        return redirect()
            ->route('loan.bulksms.logs')
            ->with('status', sprintf('Sent %d message(s). Charged %s %s.', $result['sent'], number_format($result['charged'], 2), $bulkSms->currency()));
    }

    public function templatesIndex(): View
    {
        $templates = SmsTemplate::query()
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('loan.bulksms.templates.index', compact('templates'));
    }

    public function templatesCreate(): View
    {
        return view('loan.bulksms.templates.create');
    }

    public function templatesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $validated['user_id'] = $request->user()?->id;
        SmsTemplate::create($validated);

        return redirect()
            ->route('loan.bulksms.templates.index')
            ->with('status', 'Template saved.');
    }

    public function templatesEdit(SmsTemplate $sms_template): View
    {
        return view('loan.bulksms.templates.edit', ['template' => $sms_template]);
    }

    public function templatesUpdate(Request $request, SmsTemplate $sms_template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $sms_template->update($validated);

        return redirect()
            ->route('loan.bulksms.templates.index')
            ->with('status', 'Template updated.');
    }

    public function templatesDestroy(SmsTemplate $sms_template): RedirectResponse
    {
        $sms_template->delete();

        return redirect()
            ->route('loan.bulksms.templates.index')
            ->with('status', 'Template removed.');
    }

    public function logs(): View
    {
        $logs = SmsLog::query()
            ->with(['user', 'schedule'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('loan.bulksms.logs', compact('logs'));
    }

    public function wallet(BulkSmsService $bulkSms): View
    {
        $topups = SmsWalletTopup::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('loan.bulksms.wallet', [
            'balance' => $bulkSms->walletBalance(),
            'currency' => $bulkSms->currency(),
            'costPerSms' => $bulkSms->costPerSms(),
            'topups' => $topups,
        ]);
    }

    public function walletTopup(Request $request, BulkSmsService $bulkSms): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $bulkSms->topup((float) $validated['amount'], $validated['reference'] ?? null, $validated['notes'] ?? null);

        return redirect()
            ->route('loan.bulksms.wallet')
            ->with('status', 'Wallet topped up.');
    }

    public function schedules(): View
    {
        $schedules = SmsSchedule::query()
            ->with(['user', 'template'])
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return view('loan.bulksms.schedules', compact('schedules'));
    }

    public function schedulesCancel(SmsSchedule $sms_schedule): RedirectResponse
    {
        if ($sms_schedule->status !== 'pending') {
            return redirect()
                ->route('loan.bulksms.schedules')
                ->withErrors(['status' => 'Only pending schedules can be cancelled.']);
        }

        $sms_schedule->update([
            'status' => 'cancelled',
            'processed_at' => now(),
            'failure_reason' => 'Cancelled by user.',
        ]);

        return redirect()
            ->route('loan.bulksms.schedules')
            ->with('status', 'Schedule cancelled.');
    }
}
