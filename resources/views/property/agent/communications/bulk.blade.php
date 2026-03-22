<x-property.workspace
    title="Bulk messaging"
    subtitle="Record a bulk campaign intent — delivery still requires your SMS/email gateway."
    back-route="property.communications.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    empty-title="No bulk jobs logged"
    empty-hint="Describe the segment and message plan below."
>
    <x-slot name="above">
        <form method="post" action="{{ route('property.communications.bulk.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Log bulk job</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Segment label</label>
                <input type="text" name="segment_label" value="{{ old('segment_label') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. All tenants — Block A" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes / copy plan</label>
                <textarea name="notes" rows="5" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save to log</button>
        </form>
    </x-slot>
</x-property.workspace>
