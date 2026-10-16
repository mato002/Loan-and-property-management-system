<x-property-layout>
    <x-slot name="header">Unmatched Payments</x-slot>

    <x-property.page
        title="Unmatched Payments"
        subtitle="Credits that could not be auto-linked to a tenant — bank statement, M-Pesa SMS, paybill API, or manual ingest. Assign a tenant here."
    >
        <div
            x-data="{ printOpen: false, printUrl: '{{ route('property.equity.unmatched.print', request()->query()) }}', assignOpen: false, assignAction: '', assignItem: {} }"
            class="space-y-4"
        >
        <form method="get" data-auto-apply-filters="true" class="mb-4 flex flex-wrap items-end gap-2 lg:flex-nowrap">
            <div class="min-w-[14rem] flex-1">
                <label class="text-xs text-slate-500">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Txn, phone, reason..." x-on:input.debounce.450ms="$el.form.requestSubmit()" class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div class="w-36 shrink-0">
                <label class="text-xs text-slate-500">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div class="w-36 shrink-0">
                <label class="text-xs text-slate-500">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div class="w-40 shrink-0">
                <label class="text-xs text-slate-500">Source</label>
                <select name="source" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="">All sources</option>
                    @forelse (($sourceOptions ?? []) as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['source'] ?? '') === (string) $value)>{{ $label }}</option>
                    @empty
                        <option value="equity" @selected(($filters['source'] ?? '') === 'equity')>Equity</option>
                        <option value="sms_forwarder" @selected(($filters['source'] ?? '') === 'sms_forwarder')>SMS ingest</option>
                        <option value="statement_import" @selected(($filters['source'] ?? '') === 'statement_import')>Bank statement</option>
                    @endforelse
                </select>
            </div>
            <div class="w-28 shrink-0">
                <label class="text-xs text-slate-500">Per page</label>
                <select name="per_page" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    @foreach ([10, 30, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 100) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('property.equity.unmatched') }}" class="shrink-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </form>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <form method="post" action="{{ route('property.equity.unmatched.rematch_all', request()->query()) }}" data-swal-confirm="Auto re-match all currently filtered unmatched payments?">
                @csrf
                <button
                    type="submit"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Auto re-match all
                </button>
            </form>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'csv'])),
                'xlsUrl' => route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'xls'])),
                'pdfUrl' => route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'pdf'])),
            ])
            <a href="{{ route('property.equity.matched', request()->only(['q', 'from', 'to', 'source'])) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                View Matched Payments
            </a>
            <button
                type="button"
                @click="printOpen = true"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Print
            </button>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[72rem] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold">Date</th>
                        <th class="px-4 py-3 text-left font-bold">Transaction</th>
                        <th class="px-4 py-3 text-right font-bold">Amount</th>
                        <th class="px-4 py-3 text-left font-bold">Account</th>
                        <th class="px-4 py-3 text-left font-bold">Phone</th>
                        <th class="px-4 py-3 text-left font-bold">Source</th>
                        <th class="px-4 py-3 text-left font-bold min-w-[20rem]">Message</th>
                        <th class="px-4 py-3 text-left font-bold">Reason</th>
                        <th class="px-4 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-3">{{ optional($item->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">{{ $item->transaction_id }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $item->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $item->account_number ?: '—' }}</td>
                            <td class="px-4 py-3"><x-phone-link :value="$item->phone" /></td>
                            <td class="px-4 py-3">{{ $item->source_label ?? 'Equity' }}</td>
                            <td class="px-4 py-3 align-top">
                                @if (! empty($item->display_message))
                                    <div class="whitespace-pre-wrap break-words text-xs leading-relaxed text-slate-700">{{ $item->display_message }}</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item->reason }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="post" action="{{ route('property.equity.unmatched.rematch', $item) }}" data-turbo="false" class="inline-block">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100"
                                    >
                                        Auto re-match
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    data-assign-url="{{ route('property.equity.unmatched.assign', array_merge(['unassignedPayment' => $item], request()->query())) }}"
                                    data-transaction="{{ $item->transaction_id }}"
                                    data-amount="{{ number_format((float) $item->amount, 2) }}"
                                    data-date="{{ optional($item->created_at)->format('Y-m-d H:i') }}"
                                    data-account="{{ $item->account_number ?: '—' }}"
                                    data-phone="{{ $item->phone ?: '—' }}"
                                    data-reason="{{ $item->reason }}"
                                    @click="assignAction = $event.currentTarget.dataset.assignUrl; assignItem = $event.currentTarget.dataset; assignOpen = true"
                                    class="ml-2 inline-flex items-center rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700"
                                >
                                    Assign
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">No unmatched transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                Showing {{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}
            </p>
            {{ $items->links() }}
        </div>

        <div
            x-show="assignOpen"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="assignOpen = false"
            @click.self="assignOpen = false"
            class="fixed inset-0 z-[8000] flex items-center justify-center bg-slate-950/55 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="unmatched-assign-title"
        >
            <section class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 id="unmatched-assign-title" class="text-lg font-bold text-slate-900">Assign unposted payment</h2>
                        <p class="mt-1 text-sm text-slate-500">Select the tenant and post this transaction to their account.</p>
                    </div>
                    <button type="button" @click="assignOpen = false" class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100" aria-label="Close">×</button>
                </header>
                <form method="POST" :action="assignAction" data-turbo-frame="property-main" class="space-y-4 p-5">
                    @csrf
                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-3">
                        <div><div class="text-xs text-slate-500">Transaction</div><div class="mt-1 break-all font-semibold text-slate-900" x-text="assignItem.transaction"></div></div>
                        <div><div class="text-xs text-slate-500">Amount</div><div class="mt-1 font-semibold text-slate-900" x-text="assignItem.amount"></div></div>
                        <div><div class="text-xs text-slate-500">Date</div><div class="mt-1 font-semibold text-slate-900" x-text="assignItem.date"></div></div>
                        <div><div class="text-xs text-slate-500">Account</div><div class="mt-1 font-semibold text-slate-900" x-text="assignItem.account"></div></div>
                        <div><div class="text-xs text-slate-500">Phone</div><div class="mt-1 font-semibold text-slate-900" x-text="assignItem.phone"></div></div>
                        <div class="col-span-2 sm:col-span-1"><div class="text-xs text-slate-500">Reason</div><div class="mt-1 font-semibold text-slate-900" x-text="assignItem.reason"></div></div>
                    </div>
                    <div>
                        <label for="unmatched-assign-tenant" class="text-xs font-bold uppercase tracking-widest text-slate-600">Assign to tenant</label>
                        <select id="unmatched-assign-tenant" name="tenant_id" required class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm">
                            <option value="">Select tenant…</option>
                            @foreach ($tenants as $tenantOption)
                                <option value="{{ $tenantOption->id }}">{{ $tenantOption->name }}{{ $tenantOption->account_number ? ' • '.$tenantOption->account_number : '' }}{{ $tenantOption->phone ? ' • '.$tenantOption->phone : '' }}</option>
                            @endforeach
                        </select>
                        @error('tenant_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="unmatched-assign-note" class="text-xs font-bold uppercase tracking-widest text-slate-600">Internal note (optional)</label>
                        <input id="unmatched-assign-note" type="text" name="note" value="{{ old('note') }}" maxlength="255" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" placeholder="e.g. Verified with tenant via call">
                        @error('note')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <footer class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" @click="assignOpen = false" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Assign &amp; Post Payment</button>
                    </footer>
                </form>
            </section>
        </div>

        <div
            x-show="printOpen"
            x-cloak
            class="fixed inset-0 z-[70] bg-slate-900/50 p-4"
        >
            <div class="mx-auto flex h-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-900">Print Preview - Unmatched Payments</h3>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="$refs.printFrame?.contentWindow?.print()"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                        >
                            Print
                        </button>
                        <button
                            type="button"
                            @click="printOpen = false"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Close
                        </button>
                    </div>
                </div>
                <iframe x-ref="printFrame" :src="printUrl" class="h-full w-full border-0"></iframe>
            </div>
        </div>
        </div>
    </x-property.page>
</x-property-layout>

