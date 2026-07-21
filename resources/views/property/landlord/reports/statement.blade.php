<x-property-layout>
    <x-slot name="header">Monthly statement</x-slot>

    <x-property.page title="Monthly statement">
        <form method="get" id="statement-filters" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-3 sm:p-4 grid grid-cols-2 sm:flex sm:flex-wrap items-end gap-2 sm:gap-3 w-full min-w-0">
            <div class="col-span-2 sm:col-span-1 min-w-0">
                <label class="block text-xs font-medium text-slate-500 mb-1">Statement month</label>
                <input type="month" name="month" value="{{ $month }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
            </div>
            <div class="col-span-2 sm:col-span-1 min-w-0">
                <label class="block text-xs font-medium text-slate-500 mb-1">Property</label>
                <select name="property_id" id="statement-property" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm sm:min-w-[200px]">
                    <option value="">All properties</option>
                    @foreach($filterProperties as $property)
                        <option value="{{ $property->id }}" @selected((int)($selectedPropertyId ?? 0) === (int)$property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2 sm:col-span-1 min-w-0">
                <label class="block text-xs font-medium text-slate-500 mb-1">Unit</label>
                <select name="unit_id" id="statement-unit" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm sm:min-w-[220px]">
                    <option value="">All units</option>
                    @foreach($filterUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((int)($selectedUnitId ?? 0) === (int)$unit->id)>
                            {{ $unit->filter_property_name ?? 'Property' }} / {{ $unit->label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="col-span-1 rounded-xl bg-emerald-600 px-3 sm:px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Apply</button>
            <a href="{{ route('property.landlord.reports.statement.export', array_filter(['month' => $month, 'property_id' => $selectedPropertyId ?? null, 'unit_id' => $selectedUnitId ?? null])) }}" data-turbo="false" class="col-span-1 rounded-xl border border-slate-200 dark:border-slate-600 px-3 sm:px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-center">Download CSV</a>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('statement-filters');
                const propertySelect = document.getElementById('statement-property');
                const unitSelect = document.getElementById('statement-unit');

                if (!form || !propertySelect || !unitSelect) return;

                propertySelect.addEventListener('change', function () {
                    unitSelect.value = '';
                    form.submit();
                });

                unitSelect.addEventListener('change', function () {
                    form.submit();
                });
            });
        </script>

        <x-property.landlord.kpi-grid>
            <x-property.landlord.kpi-card label="Opening balance" :value="$openingBalance" />
            <x-property.landlord.kpi-card label="Income billed" :value="$incomeBilled" />
            <x-property.landlord.kpi-card label="Income collected" :value="$incomeCollected" />
            <x-property.landlord.kpi-card label="Maintenance booked" :value="$maintenanceBooked" />
            <x-property.landlord.kpi-card label="Ledger credits" :value="$ledgerCredits" />
            <x-property.landlord.kpi-card label="Ledger debits" :value="$ledgerDebits" />
            <x-property.landlord.kpi-card label="Closing balance" :value="$closingBalance" emphasis wide />
        </x-property.landlord.kpi-grid>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4">
            <h3 class="text-sm font-semibold mb-3">Invoice lines ({{ $invoiceRows->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr><th class="py-2 pr-3 whitespace-normal break-words">Invoice</th><th class="py-2 pr-3 whitespace-normal break-words">Property / Unit</th><th class="py-2 pr-3 whitespace-normal break-words">Amount</th><th class="py-2 whitespace-normal break-words">Paid</th></tr>
                    </thead>
                    <tbody>
                        @forelse($invoiceRows as $i)
                            <tr class="border-b border-slate-100 dark:border-slate-700/70"><td class="py-2 pr-3">{{ $i->invoice_no }}</td><td class="py-2 pr-3">{{ $i->unit?->property?->name ?? '—' }} / {{ $i->unit?->label ?? '—' }}</td><td class="py-2 pr-3">{{ \App\Services\Property\PropertyMoney::kes((float)$i->amount) }}</td><td class="py-2">{{ \App\Services\Property\PropertyMoney::kes((float)$i->amount_paid) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-slate-500">No invoices in this month.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
