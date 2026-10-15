<x-property.workspace :compact-list="false"
    :title="$vendor->name"
    subtitle="Service provider details, recurring agreements, bills, and maintenance payment history."
    back-route="property.vendors.directory"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No jobs yet"
    empty-hint="This vendor has not been assigned maintenance work."
>
    <x-slot name="actions">
        <a href="{{ route('property.vendors.edit', $vendor, false) }}" class="inline-flex items-center rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit vendor</a>
        <form method="post" action="{{ route('property.vendors.pay_outstanding', $vendor, false) }}" class="inline-flex items-center gap-1" data-swal-title="Settle all outstanding payments?" data-swal-confirm="This will mark all eligible allocated jobs for this vendor as paid." data-swal-confirm-text="Yes, settle all">
            @csrf
            <input type="date" name="paid_date" value="{{ now()->toDateString() }}" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
            <input type="text" name="payment_note" placeholder="Bulk note/ref (optional)" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
            <input type="text" name="confirm_phrase" placeholder='Type PAY' class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
            <button type="submit" class="rounded-xl border border-blue-300 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">Pay outstanding</button>
        </form>
        @if ($b2cConfigured ?? false)
            <form method="post" action="{{ route('property.vendors.pay_outstanding_mpesa', $vendor, false) }}" class="inline-flex flex-wrap items-center gap-1" data-swal-title="Send vendor payout via M-Pesa?" data-swal-confirm="B2C will send the outstanding total to the vendor phone. Jobs settle when Safaricom confirms." data-swal-confirm-text="Send B2C">
                @csrf
                <input type="text" name="mpesa_phone" value="{{ old('mpesa_phone', $vendor->phone) }}" placeholder="M-Pesa phone" required class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
                <input type="text" name="payment_note" placeholder="Note (optional)" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
                <input type="text" name="confirm_phrase" placeholder='Type PAY' required class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
                <button type="submit" class="rounded-xl border border-teal-300 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 hover:bg-teal-100">Pay via M-Pesa B2C</button>
            </form>
        @endif
    </x-slot>

    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Vendor details</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 text-sm text-slate-700">
                <p><span class="text-slate-500">Category:</span> {{ $vendor->category ?: '—' }}</p>
                <p><span class="text-slate-500">Status:</span> {{ ucfirst((string) $vendor->status) }}</p>
                <p><span class="text-slate-500">Phone:</span> <x-phone-link :value="$vendor->phone" /></p>
                <p><span class="text-slate-500">Email:</span> {{ $vendor->email ?: '—' }}</p>
                <p><span class="text-slate-500">ID / Reg. no.:</span> {{ $vendor->registration_no ?: '—' }}</p>
                <p><span class="text-slate-500">Default property:</span> {{ $vendor->defaultProperty?->name ?? '—' }}</p>
                <p><span class="text-slate-500">Bank:</span> {{ $vendor->bank_name ?: '—' }}{{ $vendor->bank_branch_code ? ' · '.$vendor->bank_branch_code : '' }}</p>
                <p><span class="text-slate-500">Account:</span> {{ $vendor->bank_account_name ?: '—' }}{{ $vendor->bank_account_number ? ' · '.$vendor->bank_account_number : '' }}</p>
                <p><span class="text-slate-500">Creditor account:</span> {{ $vendor->creditor_account ?: '—' }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Recurring service agreements</p>
                    <p class="mt-1 text-xs text-slate-500">Set a fixed monthly provider cost for a property, then generate one payable bill per month.</p>
                </div>
                <a href="{{ route('property.accounting.payables.accounts_payable', ['vendor' => $vendor->name], false) }}" data-turbo-frame="property-main" class="text-sm font-medium text-indigo-700 hover:underline">Open bills</a>
            </div>

            <form method="post" action="{{ route('property.vendors.service_agreements.store', $vendor, false) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                @csrf
                <label class="text-xs font-medium text-slate-600 lg:col-span-2">Property
                    <select name="property_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select property…</option>
                        @foreach ($properties as $property)
                            <option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->code ? $property->code.' — ' : '' }}{{ $property->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600 lg:col-span-2">Service
                    <input name="service_name" value="{{ old('service_name') }}" placeholder="e.g. Garbage collection" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-600">Monthly amount (KES)
                    <input type="number" name="monthly_amount" value="{{ old('monthly_amount') }}" min="0.01" step="0.01" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-600">Bill day
                    <input type="number" name="billing_day" value="{{ old('billing_day', 1) }}" min="1" max="28" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-600">Starts
                    <input type="date" name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-600">Ends (optional)
                    <input type="date" name="ends_on" value="{{ old('ends_on') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-600 sm:col-span-2 lg:col-span-3">Agreement notes
                    <input name="notes" value="{{ old('notes') }}" placeholder="Terms, scope, or reference" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <div class="flex items-end"><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save agreement</button></div>
                @foreach (['property_id', 'service_name', 'monthly_amount', 'billing_day', 'starts_on', 'ends_on', 'notes'] as $field)
                    @error($field)<p class="text-xs text-red-600 sm:col-span-2 lg:col-span-6">{{ $message }}</p>@enderror
                @endforeach
            </form>

            @if (($serviceAgreements ?? collect())->isEmpty())
                <p class="mt-4 rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-500">No recurring agreements have been added for this provider.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($serviceAgreements as $agreement)
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $agreement->service_name }} · {{ $agreement->property?->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ \App\Services\Property\PropertyMoney::kes((float) $agreement->monthly_amount) }}/month · {{ $agreement->starts_on?->format('Y-m-d') }} to {{ $agreement->ends_on?->format('Y-m-d') ?? 'ongoing' }} · {{ ucfirst($agreement->status) }}</p>
                                </div>
                                @if ($agreement->status === 'active')
                                    <form method="post" action="{{ route('property.vendors.service_agreements.generate_bill', ['vendor' => $vendor, 'agreement' => $agreement], false) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <label class="text-xs text-slate-500">Bill month <input type="month" name="period" value="{{ now()->format('Y-m') }}" required class="ml-1 rounded border border-slate-300 px-2 py-1 text-sm text-slate-700" /></label>
                                        <button type="submit" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Generate bill</button>
                                    </form>
                                @endif
                            </div>
                            @if ($agreement->bills->isNotEmpty())
                                <div class="mt-3 overflow-x-auto">
                                    <table class="min-w-full text-xs">
                                        <thead class="text-left uppercase tracking-wide text-slate-500"><tr><th class="py-2 pr-4">Bill / month</th><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Amount</th><th class="py-2 pr-4">Due</th><th class="py-2 pr-4">Status</th><th class="py-2">Action</th></tr></thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($agreement->bills as $bill)
                                                <tr><td class="py-2 pr-4">{{ $bill->ezen_bill_no }}</td><td class="py-2 pr-4">{{ $bill->bill_date?->format('Y-m-d') ?? '—' }}</td><td class="py-2 pr-4">{{ \App\Services\Property\PropertyMoney::kes((float) $bill->total_amount) }}</td><td class="py-2 pr-4">{{ \App\Services\Property\PropertyMoney::kes((float) $bill->amount_due) }}</td><td class="py-2 pr-4">{{ $bill->displayPaymentStatus() }}</td><td class="py-2">@if ((float) $bill->amount_due > 0.009)<a class="font-semibold text-indigo-700 hover:underline" data-turbo-frame="property-main" href="{{ route('property.accounting.payables.payment_vouchers.create', ['bill_id' => $bill->id], false) }}">Record payment</a>@else<span class="text-emerald-700">Settled</span>@endif</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Payment history</p>
            @if (($paymentEntries ?? collect())->isEmpty())
                <p class="mt-2 text-sm text-slate-600">No vendor payments recorded yet.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Reference</th>
                                <th class="px-3 py-2">Amount</th>
                                <th class="px-3 py-2">Notes</th>
                                <th class="px-3 py-2">Recorded by</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($paymentEntries as $entry)
                                <tr>
                                    <td class="px-3 py-2">{{ $entry->entry_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $entry->reference ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ \App\Services\Property\PropertyMoney::kes((float) $entry->amount) }}</td>
                                    <td class="px-3 py-2">{{ $entry->description ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $recorderNames[$entry->recorded_by_user_id] ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-slot>
</x-property.workspace>

