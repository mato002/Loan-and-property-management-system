<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Notices</h3>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700" data-property-modal-open="showHubNoticeForm" @click="showHubNoticeForm = true">Create notice</button>
            <a href="{{ route('property.tenants.notices', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-slate-600 hover:underline self-center">Notice register</a>
        </div>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Due</th>
                <th class="px-4 py-3">Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($recentNotices ?? []) as $notice)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($notice->notice_type ?? 'notice')) }}</td>
                    <td class="px-4 py-3 capitalize">{{ $notice->status ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $notice->due_on?->format('Y-m-d') ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $notice->created_at?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No notices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
