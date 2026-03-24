<?php

namespace App\Http\Controllers\Property\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenantPortalRequest;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
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
            ? PmPayment::query()
                ->with(['allocations.invoice'])
                ->where('pm_tenant_id', $tenant->id)
                ->orderByDesc('paid_at')
                ->limit(100)
                ->get()
            : collect();

        $rows = $payments->map(fn (PmPayment $p) => [
            $p->paid_at?->format('Y-m-d H:i') ?? '—',
            $p->channel,
            PropertyMoney::kes((float) $p->amount),
            $p->external_ref ?? '—',
            $p->allocations->pluck('invoice.invoice_no')->filter()->implode(', ') ?: '—',
            ucfirst($p->status),
            $p->status === PmPayment::STATUS_COMPLETED
                ? new HtmlString(
                    '<a href="'.route('property.tenant.payments.receipts.show', $p).'" class="text-blue-600 hover:text-blue-700 font-medium">View</a> '.
                    '<span class="text-slate-400">|</span> '.
                    '<a href="'.route('property.tenant.payments.receipts.download', $p).'" class="text-blue-600 hover:text-blue-700 font-medium">Download</a>'
                )
                : '—',
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
            ? PmInvoice::query()
                ->with(['allocations.payment'])
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmInvoice::STATUS_PAID)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get()
            : collect();

        $rows = $invoices->map(function (PmInvoice $i) {
            $payment = $i->allocations
                ->pluck('payment')
                ->filter(fn ($p) => $p && $p->status === PmPayment::STATUS_COMPLETED)
                ->sortByDesc('paid_at')
                ->first();

            $receiptNo = $payment ? 'RCP-PAY-'.$payment->id : 'RCP-'.$i->id;

            return [
                $i->updated_at->format('Y-m-d'),
                $receiptNo,
                PropertyMoney::kes((float) $i->amount),
                '—',
                $i->invoice_no,
                'Not submitted',
                $payment
                    ? new HtmlString(
                        '<a href="'.route('property.tenant.payments.receipts.show', $payment).'" class="text-blue-600 hover:text-blue-700 font-medium">View</a> '.
                        '<span class="text-slate-400">|</span> '.
                        '<a href="'.route('property.tenant.payments.receipts.download', $payment).'" class="text-blue-600 hover:text-blue-700 font-medium">Download</a>'
                    )
                    : '—',
            ];
        })->all();

        return view('property.tenant.payments.receipts', [
            'stats' => [['label' => 'Receipts', 'value' => (string) $invoices->count(), 'hint' => 'Paid invoices']],
            'columns' => ['Date', 'Receipt #', 'Amount', 'Tax', 'Invoice', 'eTIMS status', 'Download'],
            'tableRows' => $rows,
        ]);
    }

    public function pay(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $balance = $tenant ? $this->openBalanceForTenant((int) $tenant->id) : 0.0;
        $methodConfig = $this->tenantPaymentMethods();

        return view('property.tenant.payments.pay', [
            'amountDue' => PropertyMoney::kes($balance),
            'paymentMethods' => $methodConfig['select'],
            'paymentMethodDetails' => $methodConfig['details'],
        ]);
    }

    public function stkIntentStore(Request $request): RedirectResponse
    {
        $tenant = $request->user()->pmTenantProfile;
        if (! $tenant) {
            return back()->withErrors(['tenant' => 'No tenant profile is linked to your account.']);
        }

        $balance = $this->openBalanceForTenant((int) $tenant->id);

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

    public function paymentStore(Request $request): RedirectResponse
    {
        $tenant = $request->user()->pmTenantProfile;
        if (! $tenant) {
            return back()->withErrors(['tenant' => 'No tenant profile is linked to your account.']);
        }

        $balance = $this->openBalanceForTenant((int) $tenant->id);
        if ($balance <= 0) {
            return back()->with('success', 'Nothing due right now — no payment was recorded.');
        }

        $data = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(array_keys($this->tenantPaymentMethods()['select']))],
            'payer_phone' => ['nullable', 'string', 'max:32'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'custom_amount' => ['nullable', 'string', 'max:32'],
        ]);

        $amount = $balance;
        if (! empty($data['custom_amount'])) {
            $parsed = (float) preg_replace('/[^\d.]/', '', $data['custom_amount']);
            if ($parsed > 0) {
                $amount = min($parsed, $balance);
            }
        }

        if ($amount <= 0) {
            return back()->withErrors(['custom_amount' => 'Enter a valid amount greater than zero.'])->withInput();
        }

        if ($data['payment_method'] === 'mpesa_stk' && empty($data['payer_phone'])) {
            return back()->withErrors(['payer_phone' => 'Phone number is required for M-Pesa STK.'])->withInput();
        }

        if ($data['payment_method'] !== 'mpesa_stk' && empty($data['external_ref'])) {
            return back()->withErrors(['external_ref' => 'Reference is required for this payment method.'])->withInput();
        }

        $bankMethods = ['kcb_bank', 'equity_bank', 'coop_bank'];
        $isPending = $data['payment_method'] === 'mpesa_stk' || in_array($data['payment_method'], $bankMethods, true);

        $payment = DB::transaction(function () use ($tenant, $data, $amount, $isPending) {
            $payment = PmPayment::query()->create([
                'pm_tenant_id' => $tenant->id,
                'channel' => $data['payment_method'],
                'amount' => $amount,
                'external_ref' => $data['external_ref'] ?? null,
                'paid_at' => $isPending ? null : now(),
                'status' => $isPending ? PmPayment::STATUS_PENDING : PmPayment::STATUS_COMPLETED,
                'meta' => [
                    'phone' => $data['payer_phone'] ?? null,
                    'submitted_at' => now()->toIso8601String(),
                ],
            ]);

            if (! $isPending) {
                $this->allocatePaymentToOpenInvoices($payment);
            }

            return $payment;
        });

        if (in_array($data['payment_method'], $bankMethods, true)) {
            $provider = match ($data['payment_method']) {
                'kcb_bank' => 'kcb',
                'equity_bank' => 'equity',
                default => 'coop',
            };

            $init = $this->initiateBankCollection(
                $provider,
                $payment,
                (string) ($data['payer_phone'] ?? ''),
                (string) ($data['external_ref'] ?? '')
            );

            $payment->update([
                'meta' => array_merge(is_array($payment->meta) ? $payment->meta : [], [
                    'bank_init' => $init,
                ]),
            ]);

            if (($init['ok'] ?? false) !== true) {
                $payment->update(['status' => PmPayment::STATUS_FAILED]);

                return back()->withErrors([
                    'payment_method' => 'Bank initiation failed: '.((string) ($init['message'] ?? 'Unknown error')),
                ])->withInput();
            }

            return back()->with(
                'success',
                (($init['message'] ?? null) ?: 'Bank payment request initiated. Complete authorization with your bank.')
            );
        }

        if ($isPending) {
            return back()->with('success', 'STK request queued for '.PropertyMoney::kes($amount).'. Complete prompt on phone to finalize.');
        }

        return back()->with('success', 'Payment recorded as completed and allocated across open invoices.');
    }

    private function openBalanceForTenant(int $tenantId): float
    {
        return (float) PmInvoice::query()
            ->where('pm_tenant_id', $tenantId)
            ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
            ->value('t');
    }

    /**
     * @return array{
     *   select: array<string,string>,
     *   details: array<int,array{label:string,provider:string,account:string,instructions:string}>
     * }
     */
    private function tenantPaymentMethods(): array
    {
        $select = [
            'mpesa_stk' => 'M-Pesa STK Push',
            'kcb_bank' => 'KCB Bank',
            'equity_bank' => 'Equity Bank',
            'coop_bank' => 'Co-op Bank',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card Payment',
            'cash' => 'Cash',
        ];

        $details = [
            [
                'label' => 'M-Pesa STK Push',
                'provider' => 'Safaricom M-Pesa',
                'account' => '',
                'instructions' => 'Choose this for prompt-to-phone checkout.',
            ],
            [
                'label' => 'KCB Bank',
                'provider' => 'KCB',
                'account' => '',
                'instructions' => 'Integrated bank checkout (requires active KCB API credentials).',
            ],
            [
                'label' => 'Equity Bank',
                'provider' => 'Equity',
                'account' => '',
                'instructions' => 'Integrated bank checkout (requires active Equity API credentials).',
            ],
            [
                'label' => 'Co-op Bank',
                'provider' => 'Co-operative Bank',
                'account' => '',
                'instructions' => 'Integrated bank checkout (requires active Co-op API credentials).',
            ],
        ];

        $raw = PropertyPortalSetting::getValue('custom_payment_methods_json', '[]');
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $customMethods = is_array($decoded) ? $decoded : [];

        foreach ($customMethods as $idx => $method) {
            if (! is_array($method)) {
                continue;
            }

            $name = trim((string) ($method['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $provider = trim((string) ($method['provider'] ?? ''));
            $account = trim((string) ($method['account'] ?? ''));
            $instructions = trim((string) ($method['instructions'] ?? ''));
            $key = 'custom_'.$idx;

            $select[$key] = $name;
            $details[] = [
                'label' => $name,
                'provider' => $provider,
                'account' => $account,
                'instructions' => $instructions,
            ];
        }

        return [
            'select' => $select,
            'details' => $details,
        ];
    }

    /**
     * @return array{ok:bool,message?:string,provider_ref?:string,raw?:array}
     */
    private function initiateBankCollection(string $provider, PmPayment $payment, string $phone, string $externalRef): array
    {
        $config = (array) config('services.property_banks.providers.'.$provider, []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $apiKey = (string) ($config['api_key'] ?? '');
        $apiSecret = (string) ($config['api_secret'] ?? '');
        $merchantCode = (string) ($config['merchant_code'] ?? '');

        if ($baseUrl === '' || $apiKey === '' || $apiSecret === '' || $merchantCode === '') {
            return ['ok' => false, 'message' => strtoupper($provider).' credentials not configured.'];
        }

        $callbackUrl = route('webhooks.property.payments.bank_callback', ['provider' => $provider]);
        $payload = [
            'merchant_code' => $merchantCode,
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'currency' => 'KES',
            'phone' => $phone !== '' ? $phone : null,
            'external_ref' => $externalRef !== '' ? $externalRef : ('PAY-'.$payment->id),
            'callback_url' => $callbackUrl,
        ];

        try {
            $response = Http::timeout((int) config('services.property_banks.timeout_seconds', 20))
                ->acceptJson()
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                    'X-Api-Secret' => $apiSecret,
                ])
                ->post($baseUrl.'/payments/collect', $payload);

            $json = $response->json();
            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => (string) (($json['message'] ?? null) ?: ('HTTP '.$response->status())),
                    'raw' => is_array($json) ? $json : [],
                ];
            }

            return [
                'ok' => true,
                'message' => (string) ($json['message'] ?? 'Request sent to bank gateway.'),
                'provider_ref' => (string) ($json['provider_ref'] ?? ''),
                'raw' => is_array($json) ? $json : [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Property bank initiation failed', [
                'provider' => $provider,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Gateway request failed: '.$e->getMessage(),
            ];
        }
    }

    private function allocatePaymentToOpenInvoices(PmPayment $payment): void
    {
        $remaining = (float) $payment->amount;
        if ($remaining <= 0) {
            return;
        }

        $openInvoices = PmInvoice::query()
            ->where('pm_tenant_id', $payment->pm_tenant_id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $invoiceRemaining = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
            if ($invoiceRemaining <= 0) {
                continue;
            }

            $allocation = min($remaining, $invoiceRemaining);
            if ($allocation <= 0) {
                continue;
            }

            PmPaymentAllocation::query()->create([
                'pm_payment_id' => $payment->id,
                'pm_invoice_id' => $invoice->id,
                'amount' => $allocation,
            ]);

            $invoice->amount_paid = (float) $invoice->amount_paid + $allocation;
            $invoice->save();
            $invoice->refreshComputedStatus();

            $remaining -= $allocation;
        }
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

    public function showReceipt(Request $request, PmPayment $payment): View
    {
        $tenant = $request->user()->pmTenantProfile;
        abort_unless($tenant, 403);
        abort_unless((int) $payment->pm_tenant_id === (int) $tenant->id, 403);
        abort_unless($payment->status === PmPayment::STATUS_COMPLETED, 404);

        $payment->loadMissing(['tenant', 'allocations.invoice']);

        return view('property.tenant.payments.receipt', [
            'payment' => $payment,
        ]);
    }

    public function downloadReceipt(Request $request, PmPayment $payment)
    {
        $tenant = $request->user()->pmTenantProfile;
        abort_unless($tenant, 403);
        abort_unless((int) $payment->pm_tenant_id === (int) $tenant->id, 403);
        abort_unless($payment->status === PmPayment::STATUS_COMPLETED, 404);

        $payment->loadMissing(['tenant', 'allocations.invoice']);

        $html = view('property.tenant.payments.receipt_download', [
            'payment' => $payment,
        ])->render();

        $fileName = 'receipt-RCP-PAY-'.$payment->id.'.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $fileName, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
