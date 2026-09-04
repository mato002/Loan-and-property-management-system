@php
    $showAdvanceFormByDefault = old('payment_form') === 'advance'
        || $errors->has('advance')
        || (old('payment_form') === 'advance' && $errors->hasAny(['pm_tenant_id', 'channel', 'amount', 'paid_at', 'external_ref', 'notes']));
@endphp
<x-property.workspace
    title="Tenant advance credits"
    subtitle="Unapplied tenant funds held as credit liability (not suspense)."
    back-route="property.revenue.overview"
    :stats="[
        ['label' => 'Total unapplied', 'value' => \App\Services\Property\PropertyMoney::kes((float) $totalUnapplied), 'hint' => 'All tenants'],
        ['label' => 'Tenants with credit', 'value' => (string) $balances->total(), 'hint' => 'This page'],
    ]"
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

    <form method="get" class="mb-4 flex flex-wrap gap-2 items-end" data-turbo-frame="property-main">
        <div>
            <label class="text-xs text-slate-500">Search tenant</label>
            <input type="search" name="q" value="{{ $filters['q'] }}" class="mt-1 rounded-lg border-slate-300 text-sm" placeholder="Name or phone">
        </div>
        <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white">Filter</button>
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Credit balance</th>
                    <th class="px-4 py-3">Updated</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balances as $row)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium">{{ $row->tenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->tenant?->phone ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums font-semibold text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes((float) $row->balance) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $row->updated_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($row->tenant)
                                <a href="{{ route('property.tenants.credit.ledger', $row->tenant, false) }}" data-turbo-frame="property-main" class="text-indigo-600 font-medium hover:underline">Ledger</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No tenant credit balances.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($balances->hasPages())
            <div class="px-4 py-3 border-t">{{ $balances->links() }}</div>
        @endif
    </div>
</x-property.workspace>
