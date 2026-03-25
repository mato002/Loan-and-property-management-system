<x-property.workspace
    title="Import tenants"
    subtitle="Upload a CSV to bulk add or update tenants (matched by email when provided)."
    back-route="property.tenants.directory"
    back-label="← Back to tenants"
>
    <x-slot name="actions">
        <a
            href="{{ route('property.tenants.import.template') }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Download CSV template
        </a>
    </x-slot>

    <div class="p-5 sm:p-6">
        @if (! empty($lastImportStats))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                <div class="font-semibold">Last import</div>
                <div class="text-sm mt-1">
                    Created: <span class="font-semibold tabular-nums">{{ $lastImportStats['created'] ?? 0 }}</span>,
                    Updated: <span class="font-semibold tabular-nums">{{ $lastImportStats['updated'] ?? 0 }}</span>,
                    Skipped: <span class="font-semibold tabular-nums">{{ $lastImportStats['skipped'] ?? 0 }}</span>,
                    Errors: <span class="font-semibold tabular-nums">{{ $lastImportStats['errors'] ?? 0 }}</span>
                </div>
            </div>
        @endif

        @if (is_array($lastImportErrors ?? null) && count($lastImportErrors) > 0)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                <div class="font-semibold">Import errors (showing up to {{ count($lastImportErrors) }})</div>
                <ul class="mt-2 list-disc pl-5 text-sm space-y-1">
                    @foreach ($lastImportErrors as $err)
                        <li class="break-words">{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="post"
            action="{{ route('property.tenants.import.store') }}"
            enctype="multipart/form-data"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3 max-w-2xl"
        >
            @csrf
            <h3 class="text-sm font-semibold text-slate-900">Upload CSV</h3>

            <div class="text-sm text-slate-600">
                Required columns:
                <span class="font-semibold text-slate-900">{{ implode(', ', $expectedColumns ?? []) }}</span>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">CSV file</label>
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
                Import tenants
            </button>
        </form>
    </div>
</x-property.workspace>

