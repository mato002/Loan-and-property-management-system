<div class="space-y-4">
    <x-property.responsive.table-wrapper>
        <table class="property-erp-table min-w-full border-collapse text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Label</th>
                    <th class="px-3 py-2">Unit</th>
                    <th class="px-3 py-2">How billed</th>
                    <th class="px-3 py-2">Month</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Notes</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charges as $c)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                        <td class="px-3 py-2 font-medium">{{ $c->label }}</td>
                        <td class="px-3 py-2">{{ $c->unit?->property?->name ?? '—' }} / {{ $c->unit?->label ?? '—' }}</td>
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
                        <td class="px-3 py-2 text-slate-600 max-w-xs truncate">{{ $c->notes ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" data-swal-confirm="Delete this charge line?">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No posted charge lines yet. Standing extras are on the Standing charges tab.</td></tr>
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
</div>
