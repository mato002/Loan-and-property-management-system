@php
    $topup = (array) ($smsTopup ?? []);
    $config = (array) ($topup['config'] ?? []);
    $canTopup = (bool) ($topup['can_topup'] ?? false);
    $recent = (array) ($topup['recent'] ?? []);
    $minAmount = (float) ($config['min_amount'] ?? 10);
    $maxAmount = (float) ($config['max_amount'] ?? 50000);
    $currency = (string) ($config['currency'] ?? 'KES');
    $defaultPhone = old('phone', (string) ($topup['default_phone'] ?? ''));
    $showForm = $canTopup || (isset($errors) && $errors->hasAny(['sms_topup', 'amount', 'phone']));
    $pendingTopupId = session('sms_topup_pending_id');
    $topupFlash = session('success');
    $showPendingNotice = filled($pendingTopupId) || (is_string($topupFlash) && str_contains(strtolower($topupFlash), 'm-pesa'));
@endphp

<div class="grid gap-4 lg:grid-cols-2 lg:items-start">
    <div id="sms-topup-card" class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Top up SMS wallet</p>
            @if ($canTopup)
                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                    {{ number_format($minAmount, 0) }}–{{ number_format($maxAmount, 0) }} {{ $currency }}
                </span>
            @endif
        </div>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            M-Pesa STK via Pradytec paybill. Approve on your phone to credit provider SMS balance.
        </p>

        @if ($canTopup)
            <form
                method="post"
                action="{{ route('property.communications.sms_topup', absolute: false) }}"
                class="mt-4 space-y-3"
                data-turbo="false"
                x-data="{ submitting: false }"
                @submit="submitting = true"
            >
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">M-Pesa phone</label>
                    <input
                        type="text"
                        name="phone"
                        value="{{ $defaultPhone }}"
                        required
                        placeholder="07XXXXXXXX"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-gray-900"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">Amount ({{ $currency }})</label>
                    <input
                        type="number"
                        name="amount"
                        value="{{ old('amount') }}"
                        min="{{ $minAmount }}"
                        max="{{ $maxAmount }}"
                        step="1"
                        required
                        placeholder="{{ number_format($minAmount, 0) }}"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-gray-900"
                    />
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="submitting"
                    data-swal-confirm="Send M-Pesa STK push for this SMS top-up?"
                >
                    <span x-show="!submitting">Send STK push</span>
                    <span x-show="submitting">Processing STK request…</span>
                </button>

                @if ($showPendingNotice)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        @if ($pendingTopupId)
                            STK sent. Complete payment on your phone, then use <strong>Refresh</strong> on the balance card to update.
                        @elseif (is_string($topupFlash))
                            {{ $topupFlash }}
                        @endif
                    </p>
                @endif
            </form>
            @error('sms_topup')
                <p class="mt-2 text-xs text-rose-700 dark:text-rose-300">{{ $message }}</p>
            @enderror
            @error('amount')
                <p class="mt-2 text-xs text-rose-700 dark:text-rose-300">{{ $message }}</p>
            @enderror
            @error('phone')
                <p class="mt-2 text-xs text-rose-700 dark:text-rose-300">{{ $message }}</p>
            @enderror
        @elseif ($showForm)
            <p class="mt-3 text-xs text-amber-800 dark:text-amber-200">
                {{ $config['error'] ?? 'SMS top-up is unavailable. Check Bulk SMS provider configuration or your permissions.' }}
            </p>
        @else
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                Top-up requires <strong>Manage communications</strong> permission and a configured Pradytec Bulk SMS API.
            </p>
        @endif
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Recent provider top-ups</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Latest Pradytec wallet transactions</p>

            @if ($recent !== [])
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="text-left text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="pb-2 pr-3 font-medium">Date</th>
                                <th class="pb-2 pr-3 font-medium">Amount</th>
                                <th class="pb-2 pr-3 font-medium">Status</th>
                                <th class="pb-2 font-medium">Receipt</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-800 dark:text-slate-200">
                            @foreach ($recent as $row)
                                @php
                                    $status = strtolower((string) ($row['status'] ?? 'unknown'));
                                    $pillClass = match ($status) {
                                        'completed', 'success', 'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
                                        'processing', 'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
                                        'failed', 'cancelled' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
                                        default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                                    };
                                    $pillLabel = match ($status) {
                                        'completed', 'success', 'paid' => 'Completed',
                                        'processing', 'pending' => 'Processing',
                                        'failed', 'cancelled' => 'Failed',
                                        default => ucfirst($status),
                                    };
                                @endphp
                                <tr class="border-t border-slate-100 dark:border-slate-700/70">
                                    <td class="py-2 pr-3 whitespace-nowrap">
                                        {{ isset($row['created_at']) ? \Illuminate\Support\Str::of((string) $row['created_at'])->replace('T', ' ')->substr(0, 16) : '—' }}
                                    </td>
                                    <td class="py-2 pr-3 whitespace-nowrap">
                                        {{ number_format((float) ($row['amount'] ?? 0), 2) }} {{ $currency }}
                                    </td>
                                    <td class="py-2 pr-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $pillClass }}">
                                            {{ $pillLabel }}
                                        </span>
                                    </td>
                                    <td class="py-2 whitespace-nowrap font-mono text-[11px]">
                                        {{ $row['mpesa_receipt'] ?: ($row['transaction_id'] ?? '—') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">No recent top-ups yet.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Provider notes</p>
            <ul class="mt-2 space-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                <li>Balance is billed on your Pradytec provider account.</li>
                <li>STK top-ups may show as Processing until M-Pesa confirms payment.</li>
                <li>Use the balance card refresh after completing payment on your phone.</li>
            </ul>
        </div>
    </div>
</div>
