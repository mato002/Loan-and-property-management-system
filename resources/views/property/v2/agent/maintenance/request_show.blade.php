@php
    $statusLabel = ucfirst(str_replace('_', ' ', (string) $requestItem->status));
    $urgencyLabel = ucfirst((string) $requestItem->urgency);
@endphp
<x-property.workspace
    :title="'Maintenance request #'.$requestItem->id"
    :subtitle="$requestItem->locationLabel()"
    back-route="property.maintenance.requests"
    :show-search="false"
    :legacy-toolbar="false"
    :stats="[
        ['label' => 'Status', 'value' => $statusLabel, 'hint' => ''],
        ['label' => 'Priority', 'value' => $urgencyLabel, 'hint' => ''],
        ['label' => 'Reported', 'value' => $requestItem->created_at?->format('Y-m-d') ?? '—', 'hint' => $requestItem->reportedBy?->name ?? ''],
        ['label' => 'Assignee', 'value' => $requestItem->assignedUser?->name ?? 'Unassigned', 'hint' => ''],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.maintenance.requests.edit', $requestItem) }}" data-turbo-frame="property-main" class="inline-flex min-h-[40px] items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Edit</a>
        @if ($canManage ?? false)
            <form method="post" action="{{ route('property.maintenance.requests.destroy', $requestItem) }}" data-turbo-frame="property-main" data-swal-title="Delete this request?" data-swal-confirm="Delete maintenance request #{{ $requestItem->id }}? Linked jobs are removed with it." data-swal-confirm-text="Yes, delete">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-[40px] items-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">Delete</button>
            </form>
        @endif
    </x-slot>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Request details</h2>
        <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-slate-500">Location</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $requestItem->locationLabel() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Category</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $requestItem->category }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Tenant</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $requestItem->pmTenant?->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Reported by</dt>
                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $requestItem->reportedBy?->name ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-slate-500">Description</dt>
                <dd class="mt-1 whitespace-pre-wrap text-slate-800 dark:text-slate-100">{{ $requestItem->description }}</dd>
            </div>
        </dl>
    </div>

    @php
        $requestFiles = $requestItem->relationLoaded('files') ? $requestItem->files : collect();
    @endphp
    <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Photos and videos</h2>
        @if ($requestFiles->isEmpty())
            <p class="mt-2 text-sm text-slate-500">No photos or videos were attached to this request.</p>
        @else
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($requestFiles as $file)
                    @php $fileUrl = route('property.maintenance.requests.files.show', [$requestItem, $file]); @endphp
                    <figure class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-50 dark:bg-slate-900/40">
                        @if ($file->isVideo())
                            <video controls preload="metadata" class="w-full max-h-80 bg-black" src="{{ $fileUrl }}"></video>
                        @else
                            <a href="{{ $fileUrl }}" target="_blank" rel="noopener">
                                <img src="{{ $fileUrl }}" alt="{{ $file->original_name }}" class="w-full max-h-80 object-contain bg-slate-100 dark:bg-slate-900" />
                            </a>
                        @endif
                        <figcaption class="px-3 py-2 text-xs text-slate-600 dark:text-slate-300 truncate">{{ $file->original_name }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Linked jobs</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Job</th>
                    <th class="px-4 py-2">Vendor</th>
                    <th class="px-4 py-2">Quote</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requestItem->jobs as $job)
                    <tr class="border-t border-slate-100 dark:border-slate-700">
                        <td class="px-4 py-2">
                            <a href="{{ route('property.maintenance.jobs.edit', $job) }}" data-turbo-frame="property-main" class="font-medium text-blue-700 hover:underline">#{{ $job->id }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $job->vendor?->name ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $job->quote_amount !== null ? number_format((float) $job->quote_amount, 2) : '—' }}</td>
                        <td class="px-4 py-2">{{ ucfirst(str_replace('_', ' ', (string) $job->status)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No jobs linked to this request.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
