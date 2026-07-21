<form
    method="post"
    action="{{ route('property.payments.store_advance') }}"
    data-turbo-frame="property-main"
    class="rounded-2xl border border-emerald-200/80 dark:border-emerald-800 bg-emerald-50/30 dark:bg-emerald-950/20 p-4 sm:p-5 space-y-3"
>
    @csrf
    <input type="hidden" name="payment_form" value="advance" />
    @if (! empty($returnTo))
        <input type="hidden" name="return_to" value="{{ $returnTo }}" />
    @endif
    <h3 class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">Record advance payment</h3>
    <p class="text-xs text-slate-600 dark:text-slate-400">
        For tenants with <span class="font-medium">no open invoice</span> or prepaying upcoming months. Any open balance is settled first; the remainder is held as <span class="font-medium">advance credit</span> and auto-applies when invoices are raised.
    </p>
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
            <x-property.quick-create-select
                selectId="advance-payment-tenant-select"
                name="pm_tenant_id"
                :required="true"
                :searchable="true"
                :options="\App\Support\Property\PmTenantSelectOptions::fromCollection($tenantsForAdvance, old('pm_tenant_id'))"
                :create="[
                    'mode' => 'ajax',
                    'title' => 'Create tenant',
                    'endpoint' => route('property.tenants.store_json'),
                    'fields' => [
                        ['name' => 'name', 'label' => 'Full name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. John Tenant'],
                        ['name' => 'phone', 'label' => 'Phone', 'required' => false, 'span' => '2', 'placeholder' => '+2547…'],
                        ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'email', 'required' => false, 'span' => '2', 'placeholder' => 'name@example.com'],
                    ],
                ]"
            />
            @error('pm_tenant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-slate-500">All tenants are listed — invoice not required.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
            <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                @foreach (['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('channel', 'cash') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
            <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Paid at</label>
            <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('paid_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">External ref</label>
            <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional for cash" />
            @error('external_ref')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes (optional)</label>
            <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" placeholder="e.g. Prepaid rent for upcoming months" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save advance payment</button>
</form>
