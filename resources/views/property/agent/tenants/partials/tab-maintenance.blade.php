@php
    $maintenanceRequests = $maintenanceRequests ?? collect();
@endphp

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Maintenance</h3>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
                data-property-modal-open="showHubMaintenanceForm"
                @click="showHubMaintenanceForm = true"
            >New request</button>
            <a href="{{ route('property.maintenance.requests', absolute: false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline self-center">All requests</a>
        </div>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">#</th>
                <th class="px-4 py-3">Unit</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Urgency</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($maintenanceRequests as $req)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">
                        <a href="{{ route('property.maintenance.requests.edit', $req, false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">#{{ $req->id }}</a>
                    </td>
                    <td class="px-4 py-3">{{ optional($req->unit?->property)->name }}/{{ optional($req->unit)->label ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $req->category ?: '—' }}</td>
                    <td class="px-4 py-3 capitalize">{{ $req->urgency ?? '—' }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($req->status ?? '—')) }}</td>
                    <td class="px-4 py-3">{{ $req->created_at?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No maintenance requests for this tenant yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
