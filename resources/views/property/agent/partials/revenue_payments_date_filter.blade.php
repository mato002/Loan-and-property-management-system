<form
    method="get"
    action="{{ route('property.revenue.payments', absolute: false) }}"
    data-turbo-frame="property-main"
    data-revenue-date-filter="payments"
    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
>
    <div>
        <p class="text-sm font-semibold text-slate-900">Collection period (received date)</p>
        <p class="mt-0.5 text-xs text-slate-500">
            Summary cards use payments <span class="font-medium">received</span> in
            <span class="font-medium text-slate-700">{{ $receivedRangeLabel ?? 'this month' }}</span>.
        </p>
    </div>
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-600">Quick range</label>
            <select name="range_months" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="0" @selected((int) ($filters['range_months'] ?? 1) === 0)>All dates</option>
                @foreach ([1 => '1 month', 2 => '2 months', 3 => '3 months', 6 => '6 months', 12 => '12 months'] as $n => $label)
                    <option value="{{ $n }}" @selected((int) ($filters['range_months'] ?? 1) === $n)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">Ending month</label>
            <input type="month" name="range_end" value="{{ $filters['range_end'] ?? now()->format('Y-m') }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
        </div>
        <span class="hidden pb-2 text-xs text-slate-400 sm:inline">or exact dates</span>
        <div>
            <label class="block text-xs font-medium text-slate-600">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
        </div>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Apply period</button>
    </div>
    <p class="text-[11px] text-slate-500">
        <span class="font-medium">Collected</span> counts completed payments only. Leave From/To empty to use quick range.
    </p>
</form>
