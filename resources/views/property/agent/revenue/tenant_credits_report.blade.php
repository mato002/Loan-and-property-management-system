@php
    $showAdvanceFormByDefault = old('payment_form') === 'advance'
        || $errors->has('advance')
        || (old('payment_form') === 'advance' && $errors->hasAny(['pm_tenant_id', 'channel', 'amount', 'paid_at', 'external_ref', 'notes']));
@endphp
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Tenant advance credits"
    subtitle="Unapplied tenant funds held as credit liability (not suspense)."
    back-route="property.revenue.overview"
    :stats="[
        ['label' => 'Total unapplied', 'value' => \App\Services\Property\PropertyMoney::kes((float) $totalUnapplied), 'hint' => 'All tenants with credit'],
        ['label' => 'Tenants with credit', 'value' => (string) $balances->total(), 'hint' => 'Matching the filters'],
    ]"
    :columns="$columns"
    :table-rows="$tableRows"
    table-min-width="1100px"
    empty-title="No tenant credit balances"
    empty-hint="Record an advance payment, or clear the filters."
>
    <x-slot name="pageModalsAttributes"
        x-data="{!! \Illuminate\Support\Js::from(['showAdvancePaymentForm' => $showAdvanceFormByDefault]) !!}"
    ></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            data-property-modal-open="showAdvancePaymentForm"
            @click="showAdvancePaymentForm = true"
        >
            <i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>
            <span>Record advance payment</span>
        </button>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showAdvancePaymentForm"
            close="showAdvancePaymentForm = false"
            name="tenant-credits-advance-payment"
            title="Record advance payment"
            max-width="3xl"
        >
            @error('advance')<p class="mb-3 text-xs text-red-600">{{ $message }}</p>@enderror
            @if (! ($advanceCreditsEnabled ?? false))
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Tenant advance credits are not enabled on this database. Run migrations for <code class="text-xs">pm_tenant_credit_*</code> tables, then retry.
                </div>
            @else
                @include('property.agent.revenue.partials.advance_payment_form_fields', [
                    'tenantsForAdvance' => $tenantsForAdvance ?? collect(),
                    'returnTo' => 'tenant_credits',
                ])
            @endif
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        <x-property.filter-toolbar
            :action="route('property.revenue.tenant_credits', false)"
            :reset-url="route('property.revenue.tenant_credits', false)"
            drawer-label="Credit filters"
            :chip-labels="[
                'q' => 'Search',
                'property_id' => 'Property',
                'min_balance' => 'Min balance',
                'sort' => 'Sort',
                'dir' => 'Order',
            ]"
        >
            <x-slot name="primary">
                <x-property.filter-field type="search" name="q" placeholder="Name, phone, or account…" :value="$filters['q'] ?? ''" wide />
                <x-property.filter-field type="select" name="property_id" label="Property" empty-option="Property: All" :options="collect($properties ?? [])->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name])->all()" :value="(string) ($filters['property_id'] ?? '')" />
                <x-property.filter-field type="number" name="min_balance" placeholder="Min balance" :value="$filters['min_balance'] ?? ''" />
                <x-property.filter-field type="select" name="sort" label="Sort" :options="[
                    ['value' => 'balance', 'label' => 'Sort: Balance'],
                    ['value' => 'name', 'label' => 'Sort: Tenant'],
                    ['value' => 'updated', 'label' => 'Sort: Updated'],
                ]" :value="$filters['sort'] ?? 'balance'" />
                <x-property.filter-field type="select" name="dir" label="Order" :options="[['value' => 'desc', 'label' => 'Desc'], ['value' => 'asc', 'label' => 'Asc']]" :value="$filters['dir'] ?? 'desc'" />
            </x-slot>
            <x-slot name="export">
                @include('property.agent.partials.export_dropdown', [
                    'csvUrl' => route('property.revenue.tenant_credits', array_merge(request()->query(), ['export' => 'csv']), false),
                    'xlsUrl' => route('property.revenue.tenant_credits', array_merge(request()->query(), ['export' => 'xls']), false),
                    'pdfUrl' => route('property.revenue.tenant_credits', array_merge(request()->query(), ['export' => 'pdf']), false),
                    'wordUrl' => route('property.revenue.tenant_credits', array_merge(request()->query(), ['export' => 'word']), false),
                ])
            </x-slot>
        </x-property.filter-toolbar>
    </x-slot>
    <x-slot name="footer">
        @if ($balances->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Showing {{ $balances->firstItem() ?? 0 }}–{{ $balances->lastItem() ?? 0 }} of {{ $balances->total() }} tenant(s)</p>
                {{ $balances->links() }}
            </div>
        @endif
    </x-slot>
</x-property.workspace>
