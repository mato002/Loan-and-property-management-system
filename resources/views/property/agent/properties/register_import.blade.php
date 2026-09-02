<x-property.workspace :compact-list="false"
    title="Import property register"
    subtitle="Upload a CSV from Passion's legacy system — one row per unit. Properties are grouped by property_name or property_code."
    back-route="property.properties.list"
    back-label="← Back to properties"
>
    <x-slot name="actions">
        <a
            href="{{ route('property.properties.register_import.template') }}"
            data-turbo="false"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Download CSV template
        </a>
    </x-slot>

    <div class="p-5 sm:p-6 space-y-4">
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-semibold">Legacy export tip</p>
            <p class="mt-1">If the old system's Property Register report fails to export (BIRT/Excel error), ask for a <strong>CSV export</strong> or copy the grid into Excel and save as CSV. Required columns: <strong>property_name</strong> and <strong>unit_label</strong>.</p>
        </div>

        @if (! empty($lastImportStats))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                <div class="font-semibold">Last import</div>
                <div class="text-sm mt-1">
                    Properties created: <span class="font-semibold tabular-nums">{{ $lastImportStats['properties_created'] ?? 0 }}</span>,
                    updated: <span class="font-semibold tabular-nums">{{ $lastImportStats['properties_updated'] ?? 0 }}</span>
                    · Units created: <span class="font-semibold tabular-nums">{{ $lastImportStats['units_created'] ?? 0 }}</span>,
                    updated: <span class="font-semibold tabular-nums">{{ $lastImportStats['units_updated'] ?? 0 }}</span>
                    · Skipped: <span class="font-semibold tabular-nums">{{ $lastImportStats['skipped'] ?? 0 }}</span>
                </div>
            </div>
        @endif

        @if (is_array($lastImportWarnings ?? null) && count($lastImportWarnings) > 0)
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-blue-900">
                <div class="font-semibold">Notes</div>
                <ul class="mt-2 list-disc pl-5 text-sm space-y-1">
                    @foreach ($lastImportWarnings as $warning)
                        <li class="break-words">{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (is_array($lastImportErrors ?? null) && count($lastImportErrors) > 0)
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900">
                <div class="font-semibold">Import errors</div>
                <ul class="mt-2 list-disc pl-5 text-sm space-y-1">
                    @foreach ($lastImportErrors as $err)
                        <li class="break-words">{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="post"
            action="{{ route('property.properties.register_import.store') }}"
            enctype="multipart/form-data"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3 max-w-2xl dark:border-slate-700 dark:bg-gray-800/80"
        >
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Upload CSV</h3>

            <div class="text-sm text-slate-600 dark:text-slate-300">
                Expected columns (aliases accepted):
                <span class="font-semibold text-slate-900 dark:text-white">{{ implode(', ', $expectedColumns ?? []) }}</span>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">CSV file</label>
                <input
                    type="file"
                    name="file"
                    accept=".csv,text/csv,text/plain"
                    required
                    class="mt-1 block w-full text-sm"
                />
                @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Import property register
            </button>
        </form>
    </div>
</x-property.workspace>
