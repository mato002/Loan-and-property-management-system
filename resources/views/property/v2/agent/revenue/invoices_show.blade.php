@php
    use App\Models\PmInvoice;
    $subtotal = (float) ($invoice->subtotal_amount ?? $invoice->amount);
    $tax = (float) ($invoice->tax_amount ?? 0);
    $discount = (float) ($invoice->discount_amount ?? 0);
    $total = (float) ($invoice->total_amount ?? $invoice->amount);
    $paid = (float) $invoice->amount_paid;
    $balance = max(0, $total - $paid);
    $statusBadge = match ($invoice->status) {
        'paid' => 'bg-emerald-100 text-emerald-700',
        'partial' => 'bg-amber-100 text-amber-700',
        'overdue' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-slate-200 text-slate-600',
        'draft' => 'bg-slate-100 text-slate-700',
        default => 'bg-blue-100 text-blue-700',
    };
    $canManage = (bool) (auth()->user()?->hasPmPermission('invoices.manage'));
    $canPay = (bool) (auth()->user()?->hasPmPermission('payments.record'));
    $canSend = (bool) (auth()->user()?->hasPmPermission('communications.manage'));
@endphp

<x-property-layout>
    <x-slot name="header">Invoice {{ $invoice->invoice_no }}</x-slot>

    <x-property.page
        title="Invoice {{ $invoice->invoice_no }}"
        subtitle="Full invoice lifecycle — payments, document, audit, credit notes."
    >
        <div class="mb-3">
            <a
                href="{{ route('property.revenue.invoices', absolute: false) }}"
                data-turbo-frame="property-main"
                data-property-nav="property.revenue.invoices"
                class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
            >
                ← Back to invoices
            </a>
        </div>

        <div class="grid gap-3 md:gap-4 lg:grid-cols-3">
            {{-- LEFT: invoice document --}}
            <div class="lg:col-span-2 space-y-3 md:space-y-4 min-w-0">
                <div class="rounded-xl md:rounded-2xl border border-slate-200 bg-white p-3 md:p-5 shadow-sm min-w-0 overflow-hidden">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $invoice->isCreditNote() ? 'Credit note' : 'Invoice document' }}</p>
                            @if ($invoice->originalInvoice)
                                <p class="text-xs text-slate-500 mt-0.5">Credit against
                                    <a href="{{ route('property.revenue.invoices.show', $invoice->originalInvoice) }}" data-turbo-frame="property-main" class="text-blue-700 hover:underline">{{ $invoice->originalInvoice->invoice_no }}</a>
                                </p>
                            @endif
                        </div>
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-[10px] sm:text-xs font-semibold uppercase {{ $statusBadge }}">{{ $invoice->status }}</span>
                        @if ($deliverySummary = $invoice->tenantDeliverySummary())
                            <p class="mt-1 text-xs font-medium text-emerald-700">{{ $deliverySummary }}</p>
                        @elseif ($invoice->tenantDeliveryPending())
                            <p class="mt-1 text-xs font-medium text-amber-700">Not emailed to tenant yet</p>
                        @endif
                    </div>

                    <x-property.responsive.quick-action-grid class="mt-3">
                        <a href="{{ route('property.revenue.invoices.pdf', $invoice) }}" target="_blank" rel="noopener" class="quick-action-btn border border-slate-300 text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> PDF
                        </a>
                        <a href="{{ route('property.revenue.invoices.print', $invoice) }}" target="_blank" rel="noopener" class="quick-action-btn border border-slate-300 text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-print" aria-hidden="true"></i> Print
                        </a>
                        @if ($canManage && $invoice->status !== 'paid')
                            <a href="{{ route('property.revenue.invoices.edit', $invoice) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 text-slate-700 hover:bg-slate-50">Edit</a>
                        @endif
                    </x-property.responsive.quick-action-grid>

                    <div class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 sm:grid-cols-2 text-sm min-w-0">
                        <div class="min-w-0 col-span-2 sm:col-span-1">
                            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase">Tenant</p>
                            <p class="text-slate-800 font-medium break-words">{{ $invoice->tenant?->name ?? '—' }}</p>
                            @if ($invoice->tenant?->phone)
                                <p class="text-xs text-slate-500 break-all"><a href="tel:{{ $invoice->tenant->phone }}" class="hover:underline">{{ $invoice->tenant->phone }}</a></p>
                            @endif
                            @if ($invoice->tenant?->email)
                                <p class="text-xs text-slate-500 break-all"><a href="mailto:{{ $invoice->tenant->email }}" class="hover:underline">{{ $invoice->tenant->email }}</a></p>
                            @endif
                            @if ($invoice->tenant)
                                <div class="mt-1.5 flex flex-col gap-1 text-xs sm:flex-row sm:flex-wrap sm:gap-x-2">
                                    <a href="{{ route('property.tenants.show', $invoice->tenant) }}" data-turbo-frame="property-main" class="text-blue-700 hover:underline">Profile</a>
                                    <a href="{{ route('property.tenants.statement', $invoice->tenant) }}" data-turbo-frame="property-main" class="text-blue-700 hover:underline">Statement</a>
                                    <a href="{{ route('property.revenue.arrears.tenant', $invoice->tenant) }}" data-turbo-frame="property-main" class="text-blue-700 hover:underline">Arrears</a>
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 col-span-2 sm:col-span-1">
                            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase">Property / Unit</p>
                            <p class="text-slate-800 font-medium break-words">{{ $invoice->unit?->property?->name ?? '—' }}</p>
                            <p class="text-xs text-slate-500">Unit: {{ $invoice->unit?->label ?? '—' }}</p>
                            @if ($invoice->billing_period)
                                <p class="text-xs text-slate-500">Period: {{ $invoice->billing_period }}</p>
                            @endif
                            @if ($invoice->invoice_type)
                                <p class="text-xs text-slate-500">Type: {{ ucfirst($invoice->invoice_type) }}</p>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase">Issued</p>
                            <p class="text-slate-700 tabular-nums">{{ optional($invoice->issue_date)->format('Y-m-d') ?? '—' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase">Due</p>
                            <p class="text-slate-700 tabular-nums">{{ optional($invoice->due_date)->format('Y-m-d') ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-3 md:pt-4 min-w-0">
                        <h3 class="text-sm font-semibold text-slate-800 mb-2">Line items</h3>
                        <x-property.responsive.table-wrapper min-width="520px">
                            <table class="w-full border-collapse text-xs sm:text-sm">
                                <thead class="bg-slate-50 text-left text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-2 sm:px-3 py-2 whitespace-nowrap">Description</th>
                                        <th class="px-2 sm:px-3 py-2 text-right whitespace-nowrap">Qty</th>
                                        <th class="px-2 sm:px-3 py-2 text-right whitespace-nowrap">Unit</th>
                                        <th class="px-2 sm:px-3 py-2 text-right whitespace-nowrap">Tax</th>
                                        <th class="px-2 sm:px-3 py-2 text-right whitespace-nowrap">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($invoice->items as $item)
                                        <tr>
                                            <td class="px-2 sm:px-3 py-2 text-slate-700 min-w-[8rem]">{{ $item->description }}</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ number_format((float) $item->tax_amount, 2) }}</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums font-medium text-slate-800 whitespace-nowrap">{{ number_format((float) $item->line_total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="px-2 sm:px-3 py-2 text-slate-700">{{ $invoice->description ?: 'Property charge' }}</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums text-slate-600">1</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums text-slate-600">{{ number_format((float) $invoice->amount, 2) }}</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums text-slate-600">0.00</td>
                                            <td class="px-2 sm:px-3 py-2 text-right tabular-nums font-medium text-slate-800">{{ number_format((float) $invoice->amount, 2) }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-property.responsive.table-wrapper>
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-3">
                        <div class="w-full sm:ml-auto sm:max-w-xs space-y-1 text-sm">
                            <div class="flex justify-between gap-3"><span class="text-slate-600">Subtotal</span><span class="tabular-nums shrink-0">KES {{ number_format($subtotal, 2) }}</span></div>
                            @if ($discount > 0)
                                <div class="flex justify-between gap-3"><span class="text-slate-600">Discount</span><span class="tabular-nums shrink-0">- KES {{ number_format($discount, 2) }}</span></div>
                            @endif
                            @if ($tax > 0)
                                <div class="flex justify-between gap-3"><span class="text-slate-600">Tax</span><span class="tabular-nums shrink-0">KES {{ number_format($tax, 2) }}</span></div>
                            @endif
                            <div class="flex justify-between gap-3 border-t border-slate-200 pt-1 text-base font-semibold"><span>Total</span><span class="tabular-nums shrink-0">KES {{ number_format($total, 2) }}</span></div>
                            @if ($paid > 0)
                                <div class="flex justify-between gap-3"><span class="text-slate-600">Paid</span><span class="tabular-nums shrink-0">KES {{ number_format($paid, 2) }}</span></div>
                                <div class="flex justify-between gap-3 border-t border-slate-200 pt-1 text-base font-semibold text-red-700"><span>Balance</span><span class="tabular-nums shrink-0">KES {{ number_format($balance, 2) }}</span></div>
                            @endif
                        </div>
                    </div>

                    @if ($invoice->description || $invoice->notes)
                        <div class="mt-4 border-t border-slate-100 pt-3 text-sm min-w-0">
                            @if ($invoice->description)
                                <p class="break-words"><span class="font-semibold text-slate-600">Description:</span> {{ $invoice->description }}</p>
                            @endif
                            @if ($invoice->notes)
                                <p class="mt-2 font-semibold text-slate-600">Notes:</p>
                                <p class="whitespace-pre-line text-slate-700 break-words">{{ $invoice->notes }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Payment allocations --}}
                <div class="rounded-xl md:rounded-2xl border border-slate-200 bg-white p-3 md:p-5 shadow-sm min-w-0 overflow-hidden">
                    <h3 class="text-sm font-semibold text-slate-800 mb-2 md:mb-3">Payment allocations</h3>
                    <x-property.responsive.table-wrapper min-width="560px">
                        <table class="w-full border-collapse text-xs sm:text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                            <thead class="bg-slate-50 text-left text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-2 sm:px-3 py-2 whitespace-nowrap">Payment ref</th>
                                    <th class="px-2 sm:px-3 py-2 whitespace-nowrap">Date</th>
                                    <th class="px-2 sm:px-3 py-2 whitespace-nowrap">Method</th>
                                    <th class="px-2 sm:px-3 py-2 whitespace-nowrap">Status</th>
                                    <th class="px-2 sm:px-3 py-2 text-right whitespace-nowrap">Allocated</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($invoice->allocations as $allocation)
                                    <tr>
                                        <td class="px-2 sm:px-3 py-2 text-slate-700">{{ $allocation->payment?->external_ref ?? ('PAY-'.$allocation->pm_payment_id) }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-slate-600 whitespace-nowrap">{{ optional($allocation->payment?->paid_at)->format('Y-m-d') ?? '—' }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-slate-600">{{ $allocation->payment?->channel ?? '—' }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-slate-600">{{ $allocation->payment?->status ?? '—' }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-right tabular-nums font-medium text-slate-800 whitespace-nowrap">KES {{ number_format((float) $allocation->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-8 text-center text-slate-500">No payment allocations yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-property.responsive.table-wrapper>
                </div>

                @if ($invoice->creditNotes && $invoice->creditNotes->count() > 0)
                    <div class="rounded-xl md:rounded-2xl border border-slate-200 bg-white p-3 md:p-5 shadow-sm min-w-0">
                        <h3 class="text-sm font-semibold text-slate-800 mb-2 md:mb-3">Credit notes against this invoice</h3>
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($invoice->creditNotes as $cn)
                                <li class="py-2.5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <a href="{{ route('property.revenue.invoices.show', $cn) }}" data-turbo-frame="property-main" class="text-blue-700 hover:underline font-medium break-all">{{ $cn->invoice_no }}</a>
                                    <span class="tabular-nums text-slate-700 shrink-0">KES {{ number_format(abs((float) $cn->amount), 2) }}</span>
                                    <span class="text-xs text-slate-500 shrink-0">{{ optional($cn->issue_date)->format('Y-m-d') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Activity --}}
                <div class="rounded-xl md:rounded-2xl border border-slate-200 bg-white p-3 md:p-5 shadow-sm min-w-0">
                    <h3 class="text-sm font-semibold text-slate-800 mb-2 md:mb-3">Activity</h3>
                    @if ($invoice->events && $invoice->events->count() > 0)
                        <ol class="relative border-l border-slate-200 ml-2 space-y-3 text-sm">
                            @foreach ($invoice->events as $ev)
                                <li class="ml-4 min-w-0">
                                    <div class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full bg-slate-300"></div>
                                    <p class="text-slate-800 break-words"><span class="font-semibold">{{ ucfirst(str_replace('_',' ', $ev->event)) }}</span>@if ($ev->summary) — {{ $ev->summary }}@endif</p>
                                    <p class="text-xs text-slate-500">{{ optional($ev->occurred_at)->format('Y-m-d H:i') }}@if ($ev->user) · by {{ $ev->user->name }}@endif</p>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-sm text-slate-500">No activity recorded yet.</p>
                    @endif
                </div>
            </div>

            {{-- RIGHT: actions sidebar --}}
            <div class="space-y-3 md:space-y-4 min-w-0">
                <div class="rounded-xl md:rounded-2xl border border-slate-200 bg-white p-3 md:p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800 mb-2 md:mb-3">Actions</h3>
                    <div class="space-y-2 [&_button]:min-h-[44px]">
                        @if ($canManage && $invoice->status === 'draft')
                            <form method="post" action="{{ route('property.revenue.invoices.mark_sent', $invoice) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-blue-600 text-white px-3 py-2.5 text-sm font-semibold hover:bg-blue-700"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Issue invoice</button>
                                <p class="mt-1 text-[11px] text-slate-500">Opens the bill on the ledger. Use Send to tenant below to email or SMS.</p>
                            </form>
                        @endif
                        @if ($canManage && in_array($invoice->status, ['sent','partial','overdue'], true) && (float) $invoice->amount_paid == 0)
                            <form method="post" action="{{ route('property.revenue.invoices.cancel', $invoice) }}" class="space-y-2">
                                @csrf
                                <input type="text" name="cancelled_reason" placeholder="Reason (optional)" class="w-full min-h-[44px] rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <button type="submit" class="w-full rounded-lg bg-amber-600 text-white px-3 py-2.5 text-sm font-semibold hover:bg-amber-700"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancel invoice</button>
                            </form>
                        @endif
                        @if ($canManage && $invoice->status === 'cancelled')
                            <form method="post" action="{{ route('property.revenue.invoices.reopen', $invoice) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-slate-700 text-white px-3 py-2.5 text-sm font-semibold hover:bg-slate-800"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Reopen</button>
                            </form>
                        @endif
                        @if ($canManage && $invoice->status === 'paid')
                            <details class="rounded-lg border border-slate-200" data-dropdown-root>
                                <summary data-dropdown-trigger class="cursor-pointer list-none px-3 py-2.5 min-h-[44px] text-sm font-semibold text-rose-700 flex items-center">Issue credit note…</summary>
                                <form method="post" action="{{ route('property.revenue.invoices.credit_note', $invoice) }}" class="space-y-2 p-3 border-t border-slate-100">
                                    @csrf
                                    <input type="number" step="0.01" min="0.01" max="{{ (float) $invoice->amount }}" name="amount" placeholder="Amount (KES)" required class="w-full min-h-[44px] rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input type="text" name="reason" placeholder="Reason" class="w-full min-h-[44px] rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <button type="submit" class="w-full rounded-lg bg-rose-600 text-white px-3 py-2.5 text-sm font-semibold hover:bg-rose-700 min-h-[44px]">Issue credit note</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>

                @if ($canPay && $invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                    <div id="record-payment" class="rounded-xl md:rounded-2xl border border-emerald-200 bg-emerald-50/60 p-3 md:p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-emerald-800 mb-2 md:mb-3">Record payment</h3>
                        <form method="post" action="{{ route('property.revenue.invoices.record_payment', $invoice) }}" class="space-y-2 [&_input]:min-h-[44px] [&_select]:min-h-[44px] [&_button]:min-h-[44px]">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Amount (KES)</label>
                                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $balance }}" value="{{ number_format($balance, 2, '.', '') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Channel</label>
                                <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="cash">Cash</option>
                                    <option value="mpesa">M-Pesa</option>
                                    <option value="bank">Bank</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Reference (required if not cash)</label>
                                <input type="text" name="external_ref" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="M-Pesa code / cheque #">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Paid at</label>
                                <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-emerald-600 text-white px-3 py-2.5 text-sm font-semibold hover:bg-emerald-700">Record payment</button>
                        </form>
                    </div>
                @endif

                @if ($canSend && $invoice->status !== 'draft' && $invoice->status !== 'cancelled')
                    <div class="rounded-xl md:rounded-2xl border border-slate-200 bg-white p-3 md:p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-800 mb-2 md:mb-3">Send to tenant</h3>
                        <form method="post" action="{{ route('property.revenue.invoices.send', $invoice) }}" class="space-y-2 [&_input]:min-h-[44px] [&_select]:min-h-[44px] [&_textarea]:w-full [&_button]:min-h-[44px]">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Channel</label>
                                <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="email">Email (with PDF attached)</option>
                                    <option value="sms">SMS (with public link)</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Override email (optional)</label>
                                <input type="email" name="override_email" value="" placeholder="{{ $invoice->tenant?->email ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Override phone (optional)</label>
                                <input type="text" name="override_phone" value="" placeholder="{{ $invoice->tenant?->phone ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Custom message (optional)</label>
                                <textarea name="message" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Defaults to a polite reminder with the public link"></textarea>
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-indigo-600 text-white px-3 py-2.5 text-sm font-semibold hover:bg-indigo-700">Send</button>
                        </form>

                        @if ($sharedUrl)
                            <p class="mt-3 text-xs text-slate-600 break-words">
                                Public link: <a href="{{ $sharedUrl }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline break-all">{{ $sharedUrl }}</a>
                            </p>
                        @endif
                    </div>
                @endif

                @if ($canManage)
                    <div class="rounded-xl md:rounded-2xl border border-rose-200 bg-rose-50/50 p-3 md:p-4 shadow-sm">
                        <h4 class="text-xs font-semibold text-rose-800 uppercase mb-2">Danger zone</h4>
                        @if ((float) $invoice->amount_paid == 0 && !$invoice->allocations->count())
                            <form method="post" action="{{ route('property.revenue.invoices.destroy', $invoice) }}" data-swal-confirm="Delete this invoice? This will reverse any journal entries.">
                                @csrf
                                @method('delete')
                                <button type="submit" class="w-full min-h-[44px] rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">Delete invoice</button>
                            </form>
                        @else
                            <p class="text-xs text-slate-600">Invoice has payments — use Cancel or issue a Credit note.</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </x-property.page>
</x-property-layout>
