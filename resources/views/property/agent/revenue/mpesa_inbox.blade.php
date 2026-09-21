@php
    $statusQueryConfigured = $statusQueryConfigured ?? false;
    $tableRows = $rows->getCollection()->map(function ($payment) {
        $tenant = $payment->tenant;
        $channel = (string) ($payment->channel ?? '');
        $status = (string) ($payment->status ?? '');
        $statusClass = match ($status) {
            'completed' => 'bg-emerald-100 text-emerald-800',
            'failed' => 'bg-red-100 text-red-700',
            default => 'bg-amber-100 text-amber-800',
        };

        $actions = '<a href="'.e(route('property.payments.receipt.show', $payment)).'" class="text-indigo-700 hover:text-indigo-800 font-medium">Receipt</a>';
        if ($channel === 'mpesa_stk' && $status === 'pending') {
            $actions .= '<form method="post" action="'.e(route('property.revenue.mpesa_inbox.verify_stk', $payment)).'" class="inline ml-2">'
                .csrf_field()
                .'<button type="submit" class="text-teal-700 hover:text-teal-800 font-semibold">Verify STK</button></form>';
        }

        return [
            optional($payment->paid_at ?? $payment->created_at)->format('Y-m-d H:i') ?? '—',
            $tenant?->full_name ?? ('Tenant #'.($payment->pm_tenant_id ?: '—')),
            str_replace('_', ' ', $channel),
            \App\Services\Property\PropertyMoney::kes((float) $payment->amount),
            new \Illuminate\Support\HtmlString('<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold '.$statusClass.'">'.e(ucfirst($status)).'</span>'),
            $payment->external_ref ?: '—',
            new \Illuminate\Support\HtmlString($actions),
        ];
    })->all();
@endphp
<x-property.workspace
    title="M-Pesa inbox"
    subtitle="Tenant pay-ins from STK Push, SMS ingest, and Daraja C2B confirmations. Paste a receipt to verify with Safaricom."
    back-route="property.revenue.payments"
    :stats="[
        ['label' => 'Completed today', 'value' => \App\Services\Property\PropertyMoney::kes((float) $todaySum), 'hint' => 'STK / SMS / C2B'],
        ['label' => 'Pending STK', 'value' => (string) $pendingCount, 'hint' => 'Awaiting PIN / callback'],
        ['label' => 'STK API', 'value' => ($stkConfigured ?? false) ? 'Ready' : 'Not set', 'hint' => 'MPESA_* in .env'],
        ['label' => 'Receipt verify', 'value' => $statusQueryConfigured ? 'Ready' : 'Not set', 'hint' => 'Transaction Status Query'],
    ]"
    :columns="['When', 'Tenant', 'Channel', 'Amount', 'Status', 'Receipt / Ref', '']"
    :table-rows="$tableRows"
    empty-title="No M-Pesa payments yet"
    empty-hint="Tenant STK, SMS forwarder, Paybill C2B, and verified receipts appear here."
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.payments') }}" data-turbo-frame="property-main" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">All payments</a>
        <a href="{{ route('property.equity.unmatched') }}" data-turbo-frame="property-main" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Unmatched</a>
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.mpesa_inbox') }}" class="flex flex-wrap gap-2">
            <select name="channel" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Channel: All M-Pesa</option>
                @foreach (['mpesa_stk' => 'STK Push', 'mpesa_sms_ingest' => 'SMS ingest', 'mpesa_c2b' => 'C2B Paybill', 'mpesa' => 'Manual M-Pesa'] as $v => $lab)
                    <option value="{{ $v }}" @selected(($filters['channel'] ?? '') === $v)>{{ $lab }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Status: All</option>
                @foreach (['pending', 'completed', 'failed'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
        </form>
    </x-slot>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm font-semibold text-slate-900">Verify an M-Pesa receipt</p>
        <p class="mt-1 text-xs text-slate-600">Paste the confirmation code from the tenant SMS. Safaricom confirms it asynchronously; a matching tenant payment is created when the callback arrives.</p>
        <form method="post" action="{{ route('property.revenue.mpesa_inbox.verify_receipt') }}" class="mt-3 flex flex-wrap items-end gap-2">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Receipt code</label>
                <input name="receipt" value="{{ old('receipt') }}" required maxlength="20" placeholder="QWERTY123" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono uppercase" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Phone (optional)</label>
                <input name="mpesa_phone" value="{{ old('mpesa_phone') }}" maxlength="32" placeholder="07… / 2547…" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Account / BillRef (optional)</label>
                <input name="bill_ref" value="{{ old('bill_ref') }}" maxlength="40" placeholder="Tenant account no." class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </div>
            <button type="submit" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800" @disabled(! $statusQueryConfigured)>Verify with Safaricom</button>
        </form>
        @unless ($statusQueryConfigured)
            <p class="mt-2 text-xs text-amber-800">Set <code class="font-mono">MPESA_STATUS_RESULT_URL</code> (and B2C initiator credentials) then <code class="font-mono">php artisan config:clear</code>. Missing: {{ implode('; ', $statusQueryMissing ?? []) }}</p>
        @endunless
    </div>

    @unless ($c2bConfigured ?? false)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            C2B is not fully configured. Set <code class="font-mono text-xs">MPESA_C2B_*</code> and register Validation/Confirmation URLs so Paybill payments create tenant receipts automatically.
        </div>
    @endunless
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>
