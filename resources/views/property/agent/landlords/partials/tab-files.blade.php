@php
    $docs = $landlordDocuments ?? collect();
    $periodQuery = array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']);
@endphp

<div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm w-full min-w-0 space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Files</h3>
        <p class="mt-1 text-xs text-slate-500">ID copies, PIN certificates, contracts, and other landlord documents. Stored privately — download from this page.</p>
    </div>

    <form
        method="post"
        action="{{ route('property.landlords.documents.store', $landlord) }}"
        enctype="multipart/form-data"
        class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/70 dark:bg-slate-900/40 p-4 space-y-3"
    >
        @csrf
        @foreach ($periodQuery as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
        @endforeach
        <input type="hidden" name="return_tab" value="files" />
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Document name</label>
                <input type="text" name="document_name" value="{{ old('document_name') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. National ID" />
                @error('document_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">File</label>
                <input type="file" name="document" required class="mt-1 w-full text-sm" />
                @error('document')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <textarea name="document_description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('document_description') }}</textarea>
                @error('document_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save file</button>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Size</th>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($docs as $doc)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">
                            <div class="font-medium text-slate-900 dark:text-white">{{ $doc->name }}</div>
                            @if ($doc->description)
                                <div class="text-xs text-slate-500">{{ $doc->description }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600">{{ $doc->sizeLabel() }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ optional($doc->created_at)?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('property.landlords.documents.download', [$landlord, $doc], false) }}" class="text-xs font-semibold text-indigo-700 hover:underline">Download</a>
                                <form method="post" action="{{ route('property.landlords.documents.destroy', [$landlord, $doc]) }}" data-swal-title="Delete file?" data-swal-confirm="Remove this document?" data-swal-confirm-text="Yes, delete">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-8 text-center text-slate-500">No files on this landlord yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
