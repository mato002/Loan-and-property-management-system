@php
    $vendorWorkspaceRoute = request()->routeIs('property.vendors.expense_requisitions');
    $requisitionsRoute = $vendorWorkspaceRoute ? 'property.vendors.expense_requisitions' : 'property.accounting.payables.expense_requisitions';
    $billsRoute = $vendorWorkspaceRoute ? 'property.vendors.bills' : 'property.accounting.payables.accounts_payable';
    $createVoucherRoute = 'property.accounting.payables.payment_vouchers.create';
    $approveRequisitionRoute = $vendorWorkspaceRoute ? 'property.vendors.expense_requisitions.approve' : 'property.accounting.payables.expense_requisitions.approve';
    $rejectRequisitionRoute = $vendorWorkspaceRoute ? 'property.vendors.expense_requisitions.reject' : 'property.accounting.payables.expense_requisitions.reject';
    $attachmentRoute = $vendorWorkspaceRoute ? 'property.vendors.expense_requisitions.attachment' : 'property.accounting.payables.expense_requisitions.attachment';
@endphp
<x-property.crud-shell
    title="Expense requisitions"
    subtitle="Request approval for a property expense before it becomes a payable bill. Payments then update the bill and the landlord’s property balance."
    :back-route="$billsRoute"
    :stats="[
        ['label' => 'Pending', 'value' => (string) $requisitions->where('status', 'pending')->count(), 'hint' => 'Awaiting review'],
        ['label' => 'Approved', 'value' => (string) $requisitions->where('status', 'approved')->count(), 'hint' => 'Payable created'],
        ['label' => 'Rejected', 'value' => (string) $requisitions->where('status', 'rejected')->count(), 'hint' => 'Not payable'],
    ]"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <a href="{{ route($billsRoute, absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">Bills listing</a>
        <a href="{{ route('property.vendors.directory', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">Service providers</a>
    </x-slot>

    <x-slot name="above">
        <form method="post" enctype="multipart/form-data" action="{{ route($vendorWorkspaceRoute ? 'property.vendors.expense_requisitions.store' : 'property.accounting.payables.expense_requisitions.store', absolute: false) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            @csrf
            <h2 class="text-sm font-semibold text-slate-900">New expense requisition</h2>
            <p class="mt-1 text-xs text-slate-500">An approval creates an unpaid vendor bill. Record the actual payment from that bill to post its expense once.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-medium text-slate-600">Service provider
                    <select name="pm_vendor_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Select provider…</option>@foreach ($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((string) old('pm_vendor_id', $vendorFilter ?: '') === (string) $vendor->id)>{{ $vendor->name }}</option>@endforeach</select>
                    @error('pm_vendor_id')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600">Property
                    <select name="property_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Select property…</option>@foreach ($properties as $property)<option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->code ? $property->code.' — ' : '' }}{{ $property->name }}</option>@endforeach</select>
                    @error('property_id')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600">Expense / service
                    <input name="title" value="{{ old('title') }}" required maxlength="191" placeholder="e.g. Garbage collection" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    @error('title')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600">Amount (KES)
                    <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    @error('amount')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600">Request date
                    <input name="requested_date" type="date" value="{{ old('requested_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    @error('requested_date')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600">Due date
                    <input name="due_date" type="date" value="{{ old('due_date') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    @error('due_date')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600">Provider invoice no. (optional)
                    <input name="vendor_invoice_no" value="{{ old('vendor_invoice_no') }}" maxlength="191" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    @error('vendor_invoice_no')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600">Attachment (optional, max 10 MB)
                    <input type="file" name="attachment" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    @error('attachment')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs font-medium text-slate-600 sm:col-span-2 lg:col-span-4">Description / reason
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    @error('description')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror
                </label>
            </div>
            <button class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Submit for approval</button>
        </form>

        <form method="get" action="{{ route($requisitionsRoute, absolute: false) }}" class="mt-4 flex flex-wrap items-end gap-2">
            <label class="text-xs font-medium text-slate-600">Status
                <select name="status" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach (['pending', 'approved', 'rejected'] as $option)<option value="{{ $option }}" @selected($statusFilter === $option)>{{ ucfirst($option) }}</option>@endforeach</select>
            </label>
            <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
    </x-slot>

    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Request</th><th class="px-3 py-3">Requested</th><th class="px-3 py-3">Provider</th><th class="px-3 py-3">Property</th><th class="px-3 py-3">Amount</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Requested by</th><th class="px-3 py-3">Review / action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($requisitions as $requisition)
                    <tr class="align-top">
                        <td class="px-3 py-3"><p class="font-semibold text-slate-800">{{ $requisition->title }}</p><p class="mt-1 max-w-sm text-xs text-slate-500">{{ $requisition->description ?: '—' }}</p>@if ($requisition->vendor_invoice_no)<p class="mt-1 text-xs text-slate-500">Inv. {{ $requisition->vendor_invoice_no }}</p>@endif @if ($requisition->attachment_path)<a class="mt-1 inline-block text-xs font-semibold text-indigo-700 hover:underline" href="{{ route($attachmentRoute, $requisition, false) }}">Download attachment</a>@endif</td>
                        <td class="px-3 py-3">{{ $requisition->requested_date?->format('Y-m-d') ?? '—' }}@if ($requisition->due_date)<p class="text-xs text-slate-500">Due {{ $requisition->due_date->format('Y-m-d') }}</p>@endif</td>
                        <td class="px-3 py-3">{{ $requisition->vendor?->name ?? '—' }}</td>
                        <td class="px-3 py-3">{{ $requisition->property?->name ?? '—' }}</td>
                        <td class="px-3 py-3 font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $requisition->amount) }}</td>
                        <td class="px-3 py-3">{{ ucfirst($requisition->status) }}@if ($requisition->bill)<p class="mt-1 text-xs text-slate-500">Bill {{ $requisition->bill->ezen_bill_no }}</p>@endif</td>
                        <td class="px-3 py-3">{{ $requisition->requester?->name ?? '—' }}</td>
                        <td class="px-3 py-3">
                            @if ($requisition->status === 'pending')
                                <form method="post" action="{{ route($approveRequisitionRoute, $requisition, false) }}" class="mb-2" data-swal-title="Approve expense?" data-swal-confirm="Approval creates an unpaid payable bill for this property." data-swal-confirm-text="Approve">
                                    @csrf
                                    <input name="review_note" placeholder="Review note (optional)" class="mb-1 w-48 rounded border border-slate-300 px-2 py-1 text-xs" />
                                    <button class="rounded bg-emerald-700 px-2 py-1 text-xs font-semibold text-white">Approve and create bill</button>
                                </form>
                                <form method="post" action="{{ route($rejectRequisitionRoute, $requisition, false) }}" onsubmit="return confirm('Reject this requisition? Add a reason.')">
                                    @csrf
                                    <input name="review_note" required placeholder="Reason for rejection" class="mb-1 w-48 rounded border border-slate-300 px-2 py-1 text-xs" />
                                    <button class="rounded border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700">Reject</button>
                                </form>
                            @elseif ($requisition->bill && (float) $requisition->bill->amount_due > 0.009)
                                <a href="{{ route($createVoucherRoute, ['bill_id' => $requisition->bill->id, 'return_to' => $vendorWorkspaceRoute ? 'vendors' : null], false) }}" data-turbo-frame="property-main" class="font-semibold text-indigo-700 hover:underline">Record payment</a>
                            @elseif ($requisition->review_note)
                                <span class="text-xs text-slate-500">{{ $requisition->review_note }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">No expense requisitions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.crud-shell>
