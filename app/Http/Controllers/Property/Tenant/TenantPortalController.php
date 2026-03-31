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
use App\Services\Integrations\MpesaDarajaService;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantPortalController extends Controller
{
    public function home(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $balance = 0.0;
        $rentBalance = 0.0;
        $waterBalance = 0.0;
        $due = null;
        $recentPayments = collect();
        $lastCompleted = null;
        if ($tenant) {
            $balance = (float) PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                ->value('t');
            $rentBalance = $this->openBalanceForTenant((int) $tenant->id, PmInvoice::TYPE_RENT);
            $waterBalance = $this->openBalanceForTenant((int) $tenant->id, PmInvoice::TYPE_WATER);
            $due = PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->whereColumn('amount_paid', '<', 'amount')
                ->orderBy('due_date')
                ->first()?->due_date;

            $recentPayments = PmPayment::query()
                ->where('pm_tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->limit(8)
                ->get();

            $lastCompleted = PmPayment::query()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->first();
        }

        return view('property.tenant.home', [
            'balance' => PropertyMoney::kes($balance),
            'balanceAmount' => $balance,
            'rentBalanceAmount' => $rentBalance ?? 0,
            'waterBalanceAmount' => $waterBalance ?? 0,
            'nextDue' => $due?->format('Y-m-d') ?? '—',
            'nextDueDate' => $due,
            'recentPayments' => $recentPayments,
            'lastCompletedPayment' => $lastCompleted,
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

    public function paymentsIndex(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;

        $balance = $tenant ? $this->openBalanceForTenant((int) $tenant->id) : 0.0;
        $rentBalance = $tenant ? $this->openBalanceForTenant((int) $tenant->id, PmInvoice::TYPE_RENT) : 0.0;
        $waterBalance = $tenant ? $this->openBalanceForTenant((int) $tenant->id, PmInvoice::TYPE_WATER) : 0.0;

        $nextInvoice = $tenant
            ? PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->whereColumn('amount_paid', '<', 'amount')
                ->orderBy('due_date')
                ->first()
            : null;

        $yearPaid = $tenant
            ? (float) PmPayment::query()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->whereYear('paid_at', now()->year)
                ->sum('amount')
            : 0.0;

        $onTimePct = null;
        if ($tenant) {
            $allocs = PmPaymentAllocation::query()
                ->select(['pm_payment_allocations.pm_invoice_id', 'pm_payments.paid_at', 'pm_invoices.due_date'])
                ->join('pm_payments', 'pm_payments.id', '=', 'pm_payment_allocations.pm_payment_id')
                ->join('pm_invoices', 'pm_invoices.id', '=', 'pm_payment_allocations.pm_invoice_id')
                ->where('pm_payments.pm_tenant_id', $tenant->id)
                ->where('pm_payments.status', PmPayment::STATUS_COMPLETED)
                ->whereNotNull('pm_payments.paid_at')
                ->whereNotNull('pm_invoices.due_date')
                ->orderByDesc('pm_payment_allocations.id')
                ->limit(100)
                ->get()
                ->unique('pm_invoice_id');

            $total = $allocs->count();
            if ($total > 0) {
                $onTime = $allocs->filter(function ($a) {
                    $paid = $a->paid_at ? \Carbon\Carbon::parse((string) $a->paid_at) : null;
                    $due = $a->due_date ? \Carbon\Carbon::parse((string) $a->due_date)->endOfDay() : null;
                    return $paid && $due && $paid->lte($due);
                })->count();
                $onTimePct = (int) round(($onTime / max(1, $total)) * 100);
            }
        }

        $recentPayments = $tenant
            ? PmPayment::query()
                ->where('pm_tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->limit(6)
                ->get()
            : collect();

        $openInvoices = $tenant
            ? PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->whereColumn('amount_paid', '<', 'amount')
                ->orderBy('due_date')
                ->orderBy('id')
                ->limit(30)
                ->get()
            : collect();

        return view('property.tenant.payments.index', [
            'nextDueAmount' => $nextInvoice ? (float) max(0, (float) $nextInvoice->amount - (float) $nextInvoice->amount_paid) : null,
            'nextDueDate' => $nextInvoice?->due_date,
            'outstandingBalance' => $balance,
            'rentBalance' => $rentBalance,
            'waterBalance' => $waterBalance,
            'yearPaid' => $yearPaid,
            'onTimePct' => $onTimePct,
            'recentPayments' => $recentPayments,
            'openInvoices' => $openInvoices,
        ]);
    }

    public function paymentsHistory(Request $request): View
    {
        $tenant = $request->user()->pmTenantProfile;
        $filters = [
            'status' => (string) $request->query('status', ''),
            'channel' => (string) $request->query('channel', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'q' => trim((string) $request->query('q', '')),
            'sort' => (string) $request->query('sort', 'date_desc'),
        ];

        $payments = $tenant
            ? $this->buildPaymentHistoryQuery($tenant->id, $filters)
                ->with(['allocations.invoice'])
                ->paginate(15)
                ->withQueryString()
            : PmPayment::query()->whereRaw('1 = 0')->paginate(25);

        $rows = $payments->getCollection()->map(fn (PmPayment $p) => [
            ($p->paid_at ?? $p->created_at)?->format('Y-m-d H:i') ?? '—',
            $p->channel,
            PropertyMoney::kes((float) $p->amount),
            $p->external_ref ?? '—',
            $p->allocations->pluck('invoice.invoice_no')->filter()->implode(', ') ?: '—',
            ucfirst($p->status),
            match ($p->status) {
                PmPayment::STATUS_COMPLETED => new HtmlString(
                    '<a href="'.route('property.tenant.payments.receipts.show', $p).'" class="text-blue-600 hover:text-blue-700 font-medium">View details</a> '.
                    '<span class="text-slate-400">|</span> '.
                    '<a href="'.route('property.tenant.payments.receipts.download', $p).'" data-turbo="false" class="text-blue-600 hover:text-blue-700 font-medium">Download</a> '.
                    '<span class="text-slate-400">|</span> '.
                    '<button type="button" class="text-blue-600 hover:text-blue-700 font-medium" data-copy-ref="'.e((string) ($p->external_ref ?? '')).'">Copy ref</button>'
                ),
                PmPayment::STATUS_PENDING => new HtmlString(
                    '<a href="'.route('property.tenant.payments.pending', $p).'" class="text-blue-600 hover:text-blue-700 font-medium">Open</a> '
                    .'<span class="text-slate-400">|</span> '
                    .'<a href="'.route('property.tenant.payments.pay', ['custom_amount' => (float) $p->amount]).'" class="text-blue-600 hover:text-blue-700 font-medium">Retry</a>'
                ),
                PmPayment::STATUS_FAILED => new HtmlString(
                    '<a href="'.route('property.tenant.payments.pay', ['custom_amount' => (float) $p->amount]).'" class="text-blue-600 hover:text-blue-700 font-medium">Retry</a> '
                    .'<span class="text-slate-400">|</span> '
                    .'<a href="'.route('property.tenant.requests', ['payment_id' => $p->id]).'" class="text-blue-600 hover:text-blue-700 font-medium">Report issue</a>'
                ),
                default => '—',
            },
        ])->all();

        return view('property.tenant.payments.history', [
            'stats' => [
                ['label' => 'Successful', 'value' => (string) $payments->getCollection()->where('status', 'completed')->count(), 'hint' => 'On this page'],
                ['label' => 'Pending', 'value' => (string) $payments->getCollection()->where('status', 'pending')->count(), 'hint' => 'On this page'],
                ['label' => 'Failed', 'value' => (string) $payments->getCollection()->where('status', 'failed')->count(), 'hint' => 'On this page'],
                ['label' => 'Total matches', 'value' => (string) $payments->total(), 'hint' => 'After filters'],
            ],
            'columns' => ['Date', 'Channel', 'Amount', 'Reference', 'Invoice', 'Status', 'Actions'],
            'tableRows' => $rows,
            'filters' => $filters,
            'payments' => $payments,
        ]);
    }

    public function paymentsHistoryExport(Request $request): StreamedResponse
    {
        $tenant = $request->user()->pmTenantProfile;
        abort_unless($tenant, 403);

        $filters = [
            'status' => (string) $request->query('status', ''),
            'channel' => (string) $request->query('channel', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'q' => trim((string) $request->query('q', '')),
            'sort' => (string) $request->query('sort', 'date_desc'),
        ];

        $payments = $this->buildPaymentHistoryQuery($tenant->id, $filters)
            ->with(['allocations.invoice'])
            ->limit(1000)
            ->get();

        $format = strtolower((string) $request->query('format', 'csv'));
        $rows = $payments->map(function (PmPayment $p) {
            return [
                'id' => $p->id,
                'date' => ($p->paid_at ?? $p->created_at)?->format('Y-m-d H:i:s') ?? '',
                'status' => $p->status,
                'channel' => $p->channel,
                'amount' => (string) $p->amount,
                'reference' => (string) ($p->external_ref ?? ''),
                'invoices' => $p->allocations->pluck('invoice.invoice_no')->filter()->implode('|'),
                'checkout_request_id' => (string) data_get($p->meta, 'daraja.checkout_request_id', ''),
            ];
        })->values();

        if ($format === 'json') {
            $fileName = 'tenant-payments-'.now()->format('Ymd_His').'.json';
            return response()->streamDownload(function () use ($rows) {
                echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }, $fileName, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }

        $isXls = $format === 'xls';
        $delimiter = $isXls ? "\t" : ',';
        $fileName = 'tenant-payments-'.now()->format('Ymd_His').($isXls ? '.xls' : '.csv');

        return response()->streamDownload(function () use ($rows, $delimiter) {
            $out = fopen('php://output', 'w');
            $header = ['ID', 'Date', 'Status', 'Channel', 'Amount', 'Reference', 'Invoices', 'CheckoutRequestID'];
            fputcsv($out, $header, $delimiter);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['id'],
                    $r['date'],
                    $r['status'],
                    $r['channel'],
                    $r['amount'],
                    $r['reference'],
                    $r['invoices'],
                    $r['checkout_request_id'],
                ], $delimiter);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => $isXls
                ? 'application/vnd.ms-excel; charset=UTF-8'
                : 'text/csv; charset=UTF-8',
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
                        '<a href="'.route('property.tenant.payments.receipts.download', $payment).'" data-turbo="false" class="text-blue-600 hover:text-blue-700 font-medium">Download</a>'
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
            'amountDueRaw' => $balance,
            'rentDue' => $this->openBalanceForTenant((int) $tenant?->id, PmInvoice::TYPE_RENT),
            'waterDue' => $this->openBalanceForTenant((int) $tenant?->id, PmInvoice::TYPE_WATER),
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

        $data = $request->validate([
            'mpesa_phone' => ['required', 'string', 'max:32'],
            'custom_amount' => ['nullable', 'string', 'max:32'],
            'bill_scope' => ['nullable', 'string', Rule::in(['all', 'rent', 'water'])],
        ]);

        $scope = (string) ($data['bill_scope'] ?? 'all');
        $invoiceType = $this->invoiceTypeForScope($scope);
        $balance = $this->openBalanceForTenant((int) $tenant->id, $invoiceType);

        if ($balance <= 0) {
            return back()->with('success', 'Nothing due right now — no payment request was created.');
        }

        $amount = $balance;
        if (! empty($data['custom_amount'])) {
            $parsed = (float) preg_replace('/[^\d.]/', '', $data['custom_amount']);
            if ($parsed > 0) {
                $amount = min($parsed, $balance);
            }
        }

        $daraja = app(MpesaDarajaService::class);
        $msisdn = $daraja->normalizeMsisdn((string) $data['mpesa_phone']);

        /** @var PmPayment $payment */
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa_stk',
            'amount' => $amount,
            'external_ref' => null,
            'paid_at' => null,
            'status' => PmPayment::STATUS_PENDING,
            'meta' => [
                'intent' => 'stk_push',
                'phone' => $msisdn,
                'requested_at' => now()->toIso8601String(),
                'bill_scope' => $scope,
            ],
        ]);

        if (! $daraja->isConfigured()) {
            $missing = implode(', ', $daraja->missingConfigKeys());

            return back()->withErrors([
                'mpesa_phone' => 'Daraja is not configured (missing: '.$missing.'). Update .env then run: php artisan config:clear',
            ])->withInput();
        }

        $init = $daraja->stkPush([
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round($amount),
            'PartyA' => $msisdn,
            'PartyB' => (string) config('services.mpesa.stk_shortcode'),
            'PhoneNumber' => $msisdn,
            'AccountReference' => 'PM-'.$payment->id,
            'TransactionDesc' => 'Property payment',
        ]);

        if (($init['ok'] ?? false) === true) {
            $checkout = (string) (($init['body']['CheckoutRequestID'] ?? '') ?: '');
            $merchant = (string) (($init['body']['MerchantRequestID'] ?? '') ?: '');

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['daraja'] = array_merge(is_array($meta['daraja'] ?? null) ? $meta['daraja'] : [], [
                'checkout_request_id' => $checkout,
                'merchant_request_id' => $merchant,
                'initiated_at' => now()->toIso8601String(),
                'response' => $init['body'],
            ]);
            $payment->update(['meta' => $meta]);

            return redirect()
                ->route('property.tenant.payments.pending', $payment)
                ->with('success', 'STK prompt sent to '.$msisdn.'. Check your phone to complete payment.');
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['daraja'] = array_merge(is_array($meta['daraja'] ?? null) ? $meta['daraja'] : [], [
            'initiated_at' => now()->toIso8601String(),
            'error' => $init,
        ]);
        $payment->update(['meta' => $meta]);

        return back()->withErrors([
            'mpesa_phone' => 'Daraja STK initiation failed: '
                .((string) ($init['message'] ?? 'Unknown error'))
                .' (env='.(string) ($init['env'] ?? '?').', base='.(string) ($init['base_url'] ?? '?').')',
        ])->withInput();
    }

    public function pendingPayment(PmPayment $payment): View
    {
        abort_unless($payment->pm_tenant_id === (auth()->user()?->pmTenantProfile?->id), 403);

        return view('property.tenant.payments.pending', [
            'payment' => $payment->fresh(),
        ]);
    }

    public function pendingPaymentStatus(PmPayment $payment): JsonResponse
    {
        abort_unless($payment->pm_tenant_id === (auth()->user()?->pmTenantProfile?->id), 403);

        $payment = $payment->fresh();
        $status = (string) $payment->status;
        $redirectUrl = null;
        if ($status === PmPayment::STATUS_COMPLETED) {
            $redirectUrl = route('property.tenant.payments.receipts.show', $payment);
        } elseif ($status === PmPayment::STATUS_FAILED) {
            $redirectUrl = route('property.tenant.payments.history');
        }

        return response()->json([
            'ok' => true,
            'id' => $payment->id,
            'status' => $status,
            'external_ref' => $payment->external_ref,
            'paid_at' => optional($payment->paid_at)->toIso8601String(),
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function pendingPaymentVerify(PmPayment $payment): RedirectResponse
    {
        abort_unless($payment->pm_tenant_id === (auth()->user()?->pmTenantProfile?->id), 403);

        $payment = $payment->fresh();
        if ($payment->status !== PmPayment::STATUS_PENDING) {
            return redirect()
                ->route('property.tenant.payments.pending', $payment)
                ->with('success', 'Payment status is already '.$payment->status.'.');
        }

        $checkout = (string) data_get($payment->meta, 'daraja.checkout_request_id', '');
        if ($checkout === '') {
            return back()->withErrors(['payment' => 'Missing CheckoutRequestID for this payment.']);
        }

        $daraja = app(MpesaDarajaService::class);
        $q = $daraja->stkQuery($checkout);

        // If query says completed, settle the payment even if callback didn’t arrive.
        if (($q['ok'] ?? false) === true) {
            app(PropertyPaymentSettlementService::class)->settlePending(
                $payment->id,
                'success',
                $payment->external_ref,
                now(),
                (string) ($q['message'] ?? 'Confirmed via STK query'),
                'daraja_stk_query',
                (float) $payment->amount,
            );

            return redirect()
                ->route('property.tenant.payments.receipts.show', $payment->fresh())
                ->with('success', 'Payment confirmed. Receipt is ready.');
        }

        // Handle definitive failures from query (ResultCode != 0) instead of showing generic "wait".
        $body = is_array($q['body'] ?? null) ? $q['body'] : [];
        $resultCode = (string) ($body['ResultCode'] ?? '');
        $resultDesc = (string) ($body['ResultDesc'] ?? ($q['message'] ?? 'Payment failed'));
        if ($resultCode !== '' && $resultCode !== '0') {
            app(PropertyPaymentSettlementService::class)->settlePending(
                $payment->id,
                'failed',
                null,
                null,
                $resultDesc,
                'daraja_stk_query',
                null,
            );

            return redirect()
                ->route('property.tenant.payments.history')
                ->withErrors(['payment' => 'Payment failed: '.$resultDesc.' (code '.$resultCode.').']);
        }

        $msg = (string) (($q['message'] ?? '') ?: 'Still pending. Please wait a moment then try again.');

        return redirect()
            ->route('property.tenant.payments.pending', $payment)
            ->withErrors(['payment' => 'Not confirmed yet: '.$msg]);
    }

    public function paymentStore(Request $request): RedirectResponse
    {
        $tenant = $request->user()->pmTenantProfile;
        if (! $tenant) {
            return back()->withErrors(['tenant' => 'No tenant profile is linked to your account.']);
        }

        $data = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(array_keys($this->tenantPaymentMethods()['select']))],
            'payer_phone' => ['nullable', 'string', 'max:32'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'custom_amount' => ['nullable', 'string', 'max:32'],
            'bill_scope' => ['nullable', 'string', Rule::in(['all', 'rent', 'water'])],
        ]);

        $scope = (string) ($data['bill_scope'] ?? 'all');
        $invoiceType = $this->invoiceTypeForScope($scope);
        $balance = $this->openBalanceForTenant((int) $tenant->id, $invoiceType);
        if ($balance <= 0) {
            return back()->with('success', 'Nothing due right now — no payment was recorded.');
        }

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
            return back()->withErrors(['payer_phone' => 'Phone number is required for Equity STK Push.'])->withInput();
        }

        $isPending = $data['payment_method'] === 'mpesa_stk';

        $payment = DB::transaction(function () use ($tenant, $data, $amount, $isPending, $scope, $invoiceType) {
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
                    'bill_scope' => $scope,
                ],
            ]);

            if (! $isPending) {
                $this->allocatePaymentToOpenInvoices($payment, $invoiceType);
            }

            return $payment;
        });

        if ($isPending) {
            if ($data['payment_method'] === 'mpesa_stk') {
                $daraja = app(MpesaDarajaService::class);
                $msisdn = $daraja->normalizeMsisdn((string) ($data['payer_phone'] ?? ''));

                if ($daraja->isConfigured()) {
                    $init = $daraja->stkPush([
                        'TransactionType' => 'CustomerPayBillOnline',
                        'Amount' => (int) round($amount),
                        'PartyA' => $msisdn,
                        'PartyB' => (string) config('services.mpesa.stk_shortcode'),
                        'PhoneNumber' => $msisdn,
                        'AccountReference' => 'PM-'.$payment->id,
                        'TransactionDesc' => 'Property payment',
                    ]);

                    if (($init['ok'] ?? false) === true) {
                        $checkout = (string) (($init['body']['CheckoutRequestID'] ?? '') ?: '');
                        $merchant = (string) (($init['body']['MerchantRequestID'] ?? '') ?: '');

                        $meta = is_array($payment->meta) ? $payment->meta : [];
                        $meta['phone'] = $msisdn;
                        $meta['daraja'] = array_merge(is_array($meta['daraja'] ?? null) ? $meta['daraja'] : [], [
                            'checkout_request_id' => $checkout,
                            'merchant_request_id' => $merchant,
                            'initiated_at' => now()->toIso8601String(),
                            'response' => $init['body'],
                        ]);
                        $payment->update(['meta' => $meta]);

                        return redirect()
                            ->route('property.tenant.payments.pending', $payment)
                            ->with('success', 'Equity STK Push sent to '.$msisdn.'. Check your phone to complete payment.');
                    }

                    $meta = is_array($payment->meta) ? $payment->meta : [];
                    $meta['phone'] = $msisdn;
                    $meta['daraja'] = array_merge(is_array($meta['daraja'] ?? null) ? $meta['daraja'] : [], [
                        'initiated_at' => now()->toIso8601String(),
                        'error' => $init,
                    ]);
                    $payment->update(['meta' => $meta]);

                    return back()->withErrors([
                        'payer_phone' => 'Daraja STK initiation failed: '
                            .((string) ($init['message'] ?? 'Unknown error'))
                            .' (env='.(string) ($init['env'] ?? '?').', base='.(string) ($init['base_url'] ?? '?').')',
                    ])->withInput();
                }

                $missing = implode(', ', $daraja->missingConfigKeys());
                return back()->withErrors([
                    'payer_phone' => 'Daraja is not configured (missing: '.$missing.'). Update .env then run: php artisan config:clear',
                ])->withInput();
            }

            return back()->with('success', 'Equity STK request queued for '.PropertyMoney::kes($amount).'. Complete prompt on phone to finalize.');
        }

        return back()->with('success', 'Payment recorded as completed and allocated across open invoices.');
    }

    private function openBalanceForTenant(?int $tenantId, ?string $invoiceType = null): float
    {
        if (! $tenantId) {
            return 0.0;
        }

        $query = PmInvoice::query()
            ->where('pm_tenant_id', $tenantId)
            ->whereColumn('amount_paid', '<', 'amount');
        if ($invoiceType !== null) {
            $query->where('invoice_type', $invoiceType);
        }

        return (float) $query->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')->value('t');
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
            'mpesa_stk' => 'Equity STK Push',
        ];

        $details = [
            [
                'label' => 'Equity STK Push',
                'provider' => 'Equity',
                'account' => '',
                'instructions' => 'Prompt-to-phone checkout.',
            ],
        ];

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

    private function allocatePaymentToOpenInvoices(PmPayment $payment, ?string $invoiceType = null): void
    {
        $remaining = (float) $payment->amount;
        if ($remaining <= 0) {
            return;
        }

        $openInvoices = PmInvoice::query()
            ->where('pm_tenant_id', $payment->pm_tenant_id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->when($invoiceType !== null, fn ($q) => $q->where('invoice_type', $invoiceType))
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

    private function invoiceTypeForScope(string $scope): ?string
    {
        $scope = strtolower(trim($scope));
        return match ($scope) {
            'rent' => PmInvoice::TYPE_RENT,
            'water' => PmInvoice::TYPE_WATER,
            default => null,
        };
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

        $propertyOptions = $leaseUnits
            ->map(fn ($unit) => [
                'id' => (int) $unit->property_id,
                'name' => (string) ($unit->property?->name ?? 'Property '.$unit->property_id),
            ])
            ->unique('id')
            ->values();

        $unitsByProperty = $leaseUnits
            ->groupBy(fn ($unit) => (int) $unit->property_id)
            ->map(fn ($units) => $units->map(fn ($unit) => [
                'id' => (int) $unit->id,
                'label' => (string) $unit->label,
                'property_id' => (int) $unit->property_id,
                'property_name' => (string) ($unit->property?->name ?? ''),
            ])->values())
            ->toArray();

        return view('property.tenant.maintenance.report', [
            'leaseUnits' => $leaseUnits,
            'propertyOptions' => $propertyOptions,
            'unitsByProperty' => $unitsByProperty,
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
        $allowedPropertyIds = $lease->units->pluck('property_id')->unique()->map(fn ($v) => (int) $v)->all();

        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::in($allowedPropertyIds)],
            'property_unit_id' => ['required', 'integer', Rule::in($allowedIds)],
            'category' => ['required', 'string', 'in:plumbing,electrical,security,other'],
            'description' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'string', 'in:normal,urgent,emergency'],
            'access_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $selectedUnit = $lease->units->firstWhere('id', (int) $data['property_unit_id']);
        if (! $selectedUnit || (int) $selectedUnit->property_id !== (int) $data['property_id']) {
            return back()
                ->withErrors(['property_unit_id' => 'Selected unit must belong to the selected property.'])
                ->withInput();
        }

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

    private function buildPaymentHistoryQuery(int $tenantId, array $filters)
    {
        $query = PmPayment::query()->where('pm_tenant_id', $tenantId);

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, [PmPayment::STATUS_PENDING, PmPayment::STATUS_COMPLETED, PmPayment::STATUS_FAILED], true)) {
            $query->where('status', $status);
        }

        $channel = trim((string) ($filters['channel'] ?? ''));
        if ($channel !== '') {
            $query->where('channel', $channel);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('external_ref', 'like', '%'.$search.'%')
                    ->orWhere('channel', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhere('id', $search)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.daraja.checkout_request_id')) like ?", ['%'.$search.'%']);
            });
        }

        $sort = strtolower((string) ($filters['sort'] ?? 'date_desc'));
        switch ($sort) {
            case 'date_asc':
                $query->orderBy('id');
                break;
            case 'amount_desc':
                $query->orderByDesc('amount')->orderByDesc('id');
                break;
            case 'amount_asc':
                $query->orderBy('amount')->orderByDesc('id');
                break;
            case 'status_asc':
                $query->orderBy('status')->orderByDesc('id');
                break;
            case 'status_desc':
                $query->orderByDesc('status')->orderByDesc('id');
                break;
            default:
                $query->orderByDesc('id');
        }

        return $query;
    }
}
