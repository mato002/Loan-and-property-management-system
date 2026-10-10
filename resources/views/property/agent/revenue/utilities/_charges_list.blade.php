<div class="space-y-4">
    <form method="post" action="{{ route('property.revenue.utilities.bulk_destroy') }}" data-swal-confirm="Delete selected charge lines?">
        @csrf @method('DELETE')
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" id="select-all-charges" />
                Select all
            </label>
            <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed" id="bulk-delete-charges" disabled>
                Delete selected
            </button>
        </div>
        <x-property.responsive.table-wrapper>
            <table class="property-erp-table min-w-full border-collapse text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 w-10"></th>
                        <th class="px-3 py-2">Label</th>
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Tenant</th>
                        <th class="px-3 py-2">How billed</th>
                        <th class="px-3 py-2">Month</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Notes</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($charges as $c)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                            <td class="px-3 py-2">
                                <input type="checkbox" name="charge_ids[]" value="{{ $c->id }}" class="charge-checkbox h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            </td>
                            <td class="px-3 py-2 font-medium">{{ $c->label }}</td>
                            <td class="px-3 py-2 max-w-[150px] break-words">{{ $c->unit?->property?->name ?? '—' }} / {{ $c->unit?->label ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $c->unit?->leases?->first()?->pmTenant?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ $c->billingExplanation() }}</td>
                            <td class="px-3 py-2 text-slate-700 whitespace-nowrap">
                                @php
                                    $chargeMonth = trim((string) ($c->billing_month ?? ''));
                                    $chargeMonthLabel = '—';
                                    if (preg_match('/^\d{4}-\d{2}$/', $chargeMonth) === 1) {
                                        try {
                                            $chargeMonthLabel = \Illuminate\Support\Carbon::parse($chargeMonth.'-01')->format('M Y');
                                        } catch (\Throwable) {
                                            $chargeMonthLabel = $chargeMonth;
                                        }
                                    }
                                @endphp
                                {{ $chargeMonthLabel }}
                            </td>
                            <td class="px-3 py-2 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $c->amount) }}</td>
                            <td class="px-3 py-2">
                                @if ($c->is_invoiced && $c->pm_invoice_id)
                                    <div class="flex flex-col gap-1">
                                        <a href="{{ route('property.revenue.invoices.show', $c->pm_invoice_id, false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-blue-700 hover:underline">{{ $c->invoice?->invoice_no ?? 'Invoice' }}</a>
                                        <form method="post" action="{{ route('property.revenue.invoices.cancel', $c->pm_invoice_id) }}" data-swal-confirm="Cancel this invoice? This will reverse the charge and remove the debt.">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">Reverse</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">Not invoiced</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600 max-w-xs truncate">{{ $c->notes ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @if (! $c->is_invoiced)
                                    <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" data-swal-confirm="Delete this charge line?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">Remove</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">Locked</span>
                                @endif
                            </td>
                        </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-slate-500">No posted charge lines yet. Standing extras are on the Standing charges tab.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-property.responsive.table-wrapper>

    @if (method_exists($charges, 'links'))
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
            <p>Showing {{ $charges->firstItem() ?? 0 }}–{{ $charges->lastItem() ?? 0 }} of {{ $charges->total() }}</p>
            {{ $charges->links() }}
        </div>
    @endif
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-charges');
    const chargeCheckboxes = document.querySelectorAll('.charge-checkbox');
    const bulkDeleteButton = document.getElementById('bulk-delete-charges');

    if (selectAllCheckbox && bulkDeleteButton) {
        selectAllCheckbox.addEventListener('change', function() {
            chargeCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkDeleteButton();
        });

        chargeCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkDeleteButton);
        });

        function updateBulkDeleteButton() {
            const anyChecked = Array.from(chargeCheckboxes).some(cb => cb.checked);
            bulkDeleteButton.disabled = !anyChecked;
        }
    }
});
</script>
