<div class="space-y-4">
    <div class="md:hidden space-y-2">
        @forelse ($charges as $c)
            <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $c->label }}</p>
                        <p class="text-xs text-slate-500">{{ $c->unit->property->name ?? '—' }} · {{ $c->unit->label ?? '—' }}</p>
                    </div>
                    <p class="font-bold text-slate-900 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $c->amount) }}</p>
                </div>
                <p class="mt-2 text-xs text-slate-600">{{ $c->created_at->format('Y-m-d') }} · {{ $c->notes ?: '—' }}</p>
                <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" class="mt-2" data-swal-confirm="Delete this charge line?">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs font-semibold text-rose-600">Remove</button>
                </form>
            </article>
        @empty
            <p class="text-sm text-slate-500 py-8 text-center">No charge lines yet.</p>
        @endforelse
    </div>

    <x-property.responsive.table-wrapper class="hidden md:block">
        <table class="min-w-full border-collapse text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Label</th>
                    <th class="px-3 py-2">Unit</th>
                    <th class="px-3 py-2">Usage</th>
                    <th class="px-3 py-2">Added</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Notes</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charges as $c)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                        <td class="px-3 py-2 font-medium">{{ $c->label }}</td>
                        <td class="px-3 py-2">{{ $c->unit->property->name ?? '—' }} / {{ $c->unit->label ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                            @if (($c->units_consumed ?? null) !== null || ($c->rate_per_unit ?? null) !== null || ($c->fixed_charge ?? null) !== null)
                                U {{ number_format((float) ($c->units_consumed ?? 0), 3) }} · R {{ number_format((float) ($c->rate_per_unit ?? 0), 2) }} · F {{ number_format((float) ($c->fixed_charge ?? 0), 2) }}
                            @else — @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600">{{ $c->created_at->format('Y-m-d') }}</td>
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
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No utility charges yet.</td></tr>
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
