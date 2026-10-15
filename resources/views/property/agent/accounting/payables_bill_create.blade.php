<x-property.crud-shell
    :in-property-form-modal="$inPropertyFormModal ?? false"
    title="Create vendor bill"
    subtitle="Record a supplier invoice as an unpaid payable. Record its actual payment using a payment voucher."
    back-route="property.accounting.payables.accounts_payable"
    :columns="[]"
>
    <form method="post" action="{{ route('property.accounting.payables.accounts_payable.store', absolute: false) }}" class="max-w-3xl space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-property-form-modal-alerts="1">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">Vendor <span class="text-rose-600">*</span>
                <select name="pm_supplier_id" required class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Select vendor</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected((string) old('pm_supplier_id') === (string) $vendor->id)>{{ $vendor->name }}</option>
                    @endforeach
                </select>
                @error('pm_supplier_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Property <span class="text-rose-600">*</span>
                <select name="property_id" required class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Select property</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->name }}{{ $property->code ? ' ('.$property->code.')' : '' }}</option>
                    @endforeach
                </select>
                @error('property_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Vendor invoice number
                <input name="vendor_invoice_no" maxlength="191" value="{{ old('vendor_invoice_no') }}" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
                @error('vendor_invoice_no')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Bill date <span class="text-rose-600">*</span>
                <input type="date" name="bill_date" required value="{{ old('bill_date', now()->toDateString()) }}" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
                @error('bill_date')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Due date
                <input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
                @error('due_date')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Total amount (KES) <span class="text-rose-600">*</span>
                <input type="number" name="total_amount" min="0.01" step="0.01" required value="{{ old('total_amount') }}" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
                @error('total_amount')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Description / memo <span class="text-rose-600">*</span>
                <textarea name="memo" rows="3" maxlength="500" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('memo') }}</textarea>
                @error('memo')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">This creates an unpaid supplier bill. Use a payment voucher to record payment and post the expense.</p>
        <div class="flex justify-end gap-2">
            <a href="{{ route('property.accounting.payables.accounts_payable', absolute: false) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</a>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create bill</button>
        </div>
    </form>
</x-property.crud-shell>
