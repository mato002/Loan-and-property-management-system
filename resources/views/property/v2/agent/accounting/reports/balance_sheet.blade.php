<x-property.workspace
    title="Balance sheet"
    subtitle="Assets, liabilities, and equity as at selected date."
    back-route="property.accounting.index"
    :stats="[
        ['label' => 'Assets', 'value' => \App\Services\Property\PropertyMoney::kes((float) $assets), 'hint' => 'As at '.$asAt],
        ['label' => 'Liabilities', 'value' => \App\Services\Property\PropertyMoney::kes((float) $liabilities), 'hint' => 'As at '.$asAt],
        ['label' => 'Equity', 'value' => \App\Services\Property\PropertyMoney::kes((float) $equity), 'hint' => 'As at '.$asAt],
    ]"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.reports.balance_sheet') }}" class="flex gap-2">
            <input type="date" name="as_at" value="{{ $asAt }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
        </form>
    </x-slot>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs text-slate-500">Assets</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) $assets) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs text-slate-500">Liabilities</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) $liabilities) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs text-slate-500">Equity</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) $equity) }}</p>
        </div>
    </div>
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
        What is happening: accounting position as at selected date. What needs action: investigate large swings via trial balance and journal entries.
    </div>
</x-property.workspace>

