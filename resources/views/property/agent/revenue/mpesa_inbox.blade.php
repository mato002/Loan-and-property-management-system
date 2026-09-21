@php
    $statusQueryConfigured = $statusQueryConfigured ?? false;
    $openVerify = $errors->any() || filled(old('receipt')) || request('tab') === 'verify';
@endphp
<x-property.workspace
    title="M-Pesa inbox"
    subtitle="Tenant pay-ins from STK Push, SMS ingest, and Daraja C2B. Switch tabs to browse the inbox or verify a receipt."
    back-route="property.revenue.payments"
    :stats="[
        ['label' => 'Completed today', 'value' => \App\Services\Property\PropertyMoney::kes((float) $todaySum), 'hint' => 'STK / SMS / C2B'],
        ['label' => 'Pending STK', 'value' => (string) $pendingCount, 'hint' => 'Awaiting PIN / callback'],
        ['label' => 'STK API', 'value' => ($stkConfigured ?? false) ? 'Ready' : 'Not set', 'hint' => 'MPESA_* in .env'],
        ['label' => 'Receipt verify', 'value' => $statusQueryConfigured ? 'Ready' : 'Not set', 'hint' => 'Transaction Status Query'],
    ]"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.payments') }}" data-turbo-frame="property-main" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">All payments</a>
        <a href="{{ route('property.equity.unmatched') }}" data-turbo-frame="property-main" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Unmatched</a>
    </x-slot>

    <div
        class="space-y-4"
        x-data="{ tab: @js($openVerify ? 'verify' : 'inbox') }"
    >
        <div class="flex flex-wrap gap-1 border-b border-slate-200" role="tablist">
            <button
                type="button"
                role="tab"
                @click="tab = 'inbox'"
                :aria-selected="tab === 'inbox'"
                :class="tab === 'inbox' ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-800'"
                class="border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors"
            >
                Inbox
            </button>
            <button
                type="button"
                role="tab"
                @click="tab = 'verify'"
                :aria-selected="tab === 'verify'"
                :class="tab === 'verify' ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-800'"
                class="border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors"
            >
                Verify receipt
            </button>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div x-show="tab === 'inbox'" x-cloak class="space-y-3">
            <form method="get" action="{{ route('property.revenue.mpesa_inbox') }}" class="flex flex-wrap gap-2">
                <input type="hidden" name="tab" value="inbox">
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
                <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium">Apply</button>
            </form>

            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2.5">When</th>
                            <th class="px-3 py-2.5">Tenant</th>
                            <th class="px-3 py-2.5">Channel</th>
                            <th class="px-3 py-2.5">Amount</th>
                            <th class="px-3 py-2.5">Status</th>
                            <th class="px-3 py-2.5">Receipt / Ref</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $payment)
                            @php
                                $tenant = $payment->tenant;
                                $channel = (string) ($payment->channel ?? '');
                                $status = (string) ($payment->status ?? '');
                                $statusClass = match ($status) {
                                    'completed' => 'bg-emerald-100 text-emerald-800',
                                    'failed' => 'bg-red-100 text-red-700',
                                    default => 'bg-amber-100 text-amber-800',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2.5 whitespace-nowrap text-slate-700">{{ optional($payment->paid_at ?? $payment->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-3 py-2.5 font-medium text-slate-900">{{ $tenant?->full_name ?? ('Tenant #'.($payment->pm_tenant_id ?: '—')) }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ str_replace('_', ' ', $channel) }}</td>
                                <td class="px-3 py-2.5 tabular-nums font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ ucfirst($status) }}</span>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-xs text-slate-700">{{ $payment->external_ref ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('property.payments.receipt.show', $payment) }}" class="text-indigo-700 hover:text-indigo-800 font-medium">Receipt</a>
                                    @if ($channel === 'mpesa_stk' && $status === 'pending')
                                        <form method="post" action="{{ route('property.revenue.mpesa_inbox.verify_stk', $payment) }}" class="inline ml-2">
                                            @csrf
                                            <button type="submit" class="text-teal-700 hover:text-teal-800 font-semibold">Verify STK</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-slate-500">
                                    No M-Pesa payments yet. Tenant STK, SMS forwarder, Paybill C2B, and verified receipts appear here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('property.agent.partials.pagination_controls', ['paginator' => $rows])

            @unless ($c2bConfigured ?? false)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    C2B is not fully configured. Set <code class="font-mono text-xs">MPESA_C2B_*</code> and register Validation/Confirmation URLs so Paybill payments create tenant receipts automatically.
                </div>
            @endunless
        </div>

        <div x-show="tab === 'verify'" x-cloak class="space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm max-w-3xl">
                <h2 class="text-sm font-semibold text-slate-900">Verify an M-Pesa receipt</h2>
                <p class="mt-1 text-sm text-slate-600">Paste the confirmation code from the tenant SMS. Safaricom confirms it asynchronously; a matching tenant payment is created when the callback arrives.</p>

                <form method="post" action="{{ route('property.revenue.mpesa_inbox.verify_receipt') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Receipt code <span class="text-rose-600">*</span></label>
                        <input name="receipt" value="{{ old('receipt') }}" required maxlength="20" placeholder="QWERTY123" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-mono uppercase" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Phone (optional)</label>
                        <input name="mpesa_phone" value="{{ old('mpesa_phone') }}" maxlength="32" placeholder="07… / 2547…" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Account / BillRef (optional)</label>
                        <input name="bill_ref" value="{{ old('bill_ref') }}" maxlength="40" placeholder="Tenant account no." class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50" @disabled(! $statusQueryConfigured)>
                            Verify with Safaricom
                        </button>
                    </div>
                </form>

                @unless ($statusQueryConfigured)
                    <p class="mt-3 text-xs text-amber-800">
                        Set <code class="font-mono">MPESA_STATUS_RESULT_URL</code> (and B2C initiator credentials) then <code class="font-mono">php artisan config:clear</code>.
                        Missing: {{ implode('; ', $statusQueryMissing ?? []) }}
                    </p>
                @endunless
            </div>
        </div>
    </div>
</x-property.workspace>
