@php
    $hubOpenInvoices = $hubOpenInvoices ?? collect();
    $hubLeases = $hubLeases ?? collect();
    $hubUnits = $hubUnits ?? collect();
    $advanceCreditsEnabled = $advanceCreditsEnabled ?? false;
    $noticeTemplate = $noticeTemplate ?? '';
    $tenantReturn = \App\Support\Property\TenantHubRedirect::hiddenFields((int) $tenant->id, 'overview');
    $hubTenantId = (int) $tenant->id;
    $hubLeaseOptions = $hubLeases->map(function ($l) use ($hubTenantId) {
        $unitIds = $l->units->pluck('id')->implode(',');
        $rent = (float) ($l->monthly_rent ?? 0);
        $unitSummary = $l->units
            ->map(fn ($u) => trim(($u->property?->name ?? '').' / '.$u->label, ' /'))
            ->filter()
            ->implode(', ');

        return [
            'value' => $l->id,
            'label' => $unitSummary !== '' ? "#{$l->id} · {$unitSummary}" : 'Lease #'.$l->id,
            'selected' => (string) old('pm_lease_id') === (string) $l->id,
            'attrs' => [
                'data-tenant-id' => (string) $hubTenantId,
                'data-unit-ids' => $unitIds,
                'data-rent' => (string) $rent,
            ],
        ];
    })->all();
@endphp

{{-- Create invoice --}}
<x-property.modal
    show="showHubInvoiceForm"
    close="showHubInvoiceForm = false"
    name="tenant-hub-invoice-create"
    title="Create invoice"
    max-width="3xl"
>
    <form
        method="post"
        action="{{ route('property.invoices.store') }}"
        class="space-y-3"
        data-lease-info-url="{{ route('property.invoices.lease_info', ['lease' => 'LEASE_ID'], false) }}"
        data-initial-tenant-id="{{ $tenant->id }}"
    >
        @csrf
        <input type="hidden" name="return_to" value="tenant_show" />
        <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
        <input type="hidden" name="return_tab" value="invoices" />
        <input type="hidden" name="pm_tenant_id" value="{{ $tenant->id }}" />

        <p class="text-xs text-slate-500">Billing for <span class="font-semibold text-slate-800">{{ $tenant->name }}</span>.</p>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Lease</label>
                <x-property.quick-create-select
                    selectId="hub-invoice-lease"
                    name="pm_lease_id"
                    placeholder="—"
                    :searchable="true"
                    :options="$hubLeaseOptions"
                    :create="['mode' => 'none']"
                />
                @error('pm_lease_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <x-property.quick-create-select
                    selectId="hub-invoice-unit"
                    name="property_unit_id"
                    :required="true"
                    :options="$hubUnits->map(fn ($u) => [
                        'value' => $u->id,
                        'label' => (($u->property?->name ?? 'Unknown').' / '.$u->label),
                        'selected' => (string) old('property_unit_id') === (string) $u->id,
                        'attrs' => ['data-rent' => (string) ($u->rent_amount ?? 0), 'data-unit-label' => $u->label],
                    ])->all()"
                    :create="['mode' => 'none']"
                />
                @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Issue date</label>
                <input id="hub-invoice-issue-date" type="date" name="issue_date" value="{{ old('issue_date', now()->startOfMonth()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('issue_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due date</label>
                <input id="hub-invoice-due-date" type="date" name="due_date" value="{{ old('due_date', now()->addDays(14)->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('due_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                <input id="hub-invoice-amount" type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">When to issue</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                    <option value="sent" @selected(old('status') === 'sent')>Issue now</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @include('property.agent.revenue.partials.invoice_type_field', ['selected' => old('invoice_type', 'rent')])
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Billing period</label>
                <input type="month" name="billing_period" value="{{ old('billing_period') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <input id="hub-invoice-description" type="text" name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
        </div>
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create invoice</button>
    </form>
</x-property.modal>

{{-- Record payment against invoice --}}
<x-property.modal
    show="showHubPaymentForm"
    close="showHubPaymentForm = false"
    name="tenant-hub-payment"
    title="Record payment"
    max-width="2xl"
>
    <form method="post" action="{{ route('property.payments.store') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="payment_form" value="invoice" />
        <input type="hidden" name="return_to" value="tenant_show" />
        <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
        <input type="hidden" name="return_tab" value="payments" />
        <input type="hidden" name="pm_tenant_id" value="{{ $tenant->id }}" />

        <p class="text-xs text-slate-500">Payment for <span class="font-semibold text-slate-800">{{ $tenant->name }}</span>.</p>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Open invoice</label>
                <select name="pm_invoice_id" required data-property-searchable="true" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @forelse ($hubOpenInvoices as $inv)
                        @php $open = max(0, (float) $inv->amount - (float) $inv->amount_paid); @endphp
                        <option value="{{ $inv->id }}" @selected((string) old('pm_invoice_id') === (string) $inv->id)>
                            {{ $inv->invoice_no ?: '#'.$inv->id }} — bal {{ number_format($open, 2) }}
                        </option>
                    @empty
                        <option value="" disabled>No open invoices</option>
                    @endforelse
                </select>
                @error('pm_invoice_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @if ($hubOpenInvoices->isEmpty())
                    <p class="mt-1 text-xs text-amber-700">No open invoices.
                        <button type="button" class="font-semibold underline" @click="showHubPaymentForm = false; showHubAdvanceForm = true">Record advance</button>
                        or
                        <button type="button" class="font-semibold underline" @click="showHubPaymentForm = false; showHubInvoiceForm = true">create an invoice</button>.
                    </p>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach (['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('channel', 'mpesa') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Paid at</label>
                <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">External ref</label>
                <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="M-Pesa / bank ref" />
                @error('external_ref')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @disabled($hubOpenInvoices->isEmpty())>Save payment</button>
    </form>
</x-property.modal>

{{-- Advance payment --}}
<x-property.modal
    show="showHubAdvanceForm"
    close="showHubAdvanceForm = false"
    name="tenant-hub-advance"
    title="Record advance"
    max-width="2xl"
>
    @if (! $advanceCreditsEnabled)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Tenant advance credits are not enabled on this database yet.
        </div>
    @else
        <form method="post" action="{{ route('property.payments.store_advance') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="payment_form" value="advance" />
            <input type="hidden" name="return_to" value="tenant_show" />
            <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
            <input type="hidden" name="return_tab" value="credit" />
            <input type="hidden" name="pm_tenant_id" value="{{ $tenant->id }}" />
            <p class="text-xs text-slate-500">Advance / prepay for <span class="font-semibold text-slate-800">{{ $tenant->name }}</span>. Open invoices are paid first; remainder stays as credit.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                    <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('channel', 'mpesa') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Paid at</label>
                    <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reference</label>
                    <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save advance</button>
        </form>
    @endif
</x-property.modal>

{{-- Create notice --}}
<x-property.modal
    show="showHubNoticeForm"
    close="showHubNoticeForm = false"
    name="tenant-hub-notice"
    title="New notice"
    max-width="2xl"
>
    <form method="post" action="{{ route('property.tenants.notices.store') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="return_to" value="tenant_show" />
        <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
        <input type="hidden" name="return_tab" value="notices" />
        <input type="hidden" name="pm_tenant_id" value="{{ $tenant->id }}" />

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit (optional)</label>
                <select name="property_unit_id" data-property-searchable="true" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">—</option>
                    @foreach ($hubUnits as $u)
                        <option value="{{ $u->id }}" @selected((string) old('property_unit_id') === (string) $u->id)>
                            {{ ($u->property?->name ?? '—').' / '.$u->label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Type</label>
                <input type="text" name="notice_type" value="{{ old('notice_type', 'vacate') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="vacate, rent_increase…" />
                @error('notice_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach (['draft', 'pending_approval', 'approved', 'sent', 'delivered', 'acknowledged', 'disputed', 'expired', 'cancelled', 'escalated', 'closed'] as $st)
                        <option value="{{ $st }}" @selected(old('status', 'draft') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due / response by</label>
                <input type="date" name="due_on" value="{{ old('due_on') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes', $noticeTemplate) }}</textarea>
            </div>
        </div>
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save notice</button>
    </form>
</x-property.modal>

{{-- Maintenance request --}}
<x-property.modal
    show="showHubMaintenanceForm"
    close="showHubMaintenanceForm = false"
    name="tenant-hub-maintenance"
    title="New maintenance request"
    max-width="2xl"
>
    @php
        $hubUnitPayload = $hubUnits->map(fn ($u) => [
            'id' => (string) $u->id,
            'property_id' => (string) $u->property_id,
            'label' => (string) $u->label,
            'property_name' => (string) ($u->property?->name ?? '—'),
        ])->values();
        $hubProperties = $hubUnits->pluck('property')->filter()->unique('id')->sortBy('name')->values();
        $defaultPropertyId = (string) old('property_id', optional($hubProperties->first())->id ?? '');
        $defaultUnitId = (string) old('property_unit_id', optional($hubUnits->first())->id ?? '');
    @endphp
    <form
        method="post"
        action="{{ route('property.maintenance.requests.store') }}"
        class="space-y-3"
        x-data="{
            propertyId: @js($defaultPropertyId),
            unitId: @js($defaultUnitId),
            units: @js($hubUnitPayload),
            get filteredUnits() {
                return this.units.filter(u => u.property_id === this.propertyId);
            }
        }"
    >
        @csrf
        <input type="hidden" name="return_to" value="tenant_show" />
        <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
        <input type="hidden" name="return_tab" value="maintenance" />

        @if ($hubUnits->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                This tenant has no units on file. Assign a lease first.
            </div>
        @else
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" x-model="propertyId" @change="unitId = ''" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($hubProperties as $property)
                        <option value="{{ $property->id }}">{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <select name="property_unit_id" x-model="unitId" :disabled="!propertyId" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 disabled:bg-slate-100">
                    <option value="">Select…</option>
                    <template x-for="u in filteredUnits" :key="u.id">
                        <option :value="u.id" x-text="u.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                <input type="text" name="category" value="{{ old('category') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Plumbing, electrical…" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Urgency</label>
                <select name="urgency" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="normal" @selected(old('urgency', 'normal') === 'normal')>Normal</option>
                    <option value="urgent" @selected(old('urgency') === 'urgent')>Urgent</option>
                    <option value="emergency" @selected(old('urgency') === 'emergency')>Emergency</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <textarea name="description" rows="3" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit request</button>
        @endif
    </form>
</x-property.modal>
