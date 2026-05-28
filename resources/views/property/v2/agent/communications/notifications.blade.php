<x-property.workspace
    title="Notifications"
    subtitle="In-app alerts from applications, system events, and communication activity."
    back-route="property.dashboard"
    :legacy-toolbar="false"
    :stats="$stats"
    empty-title="No notifications yet"
    empty-hint="New alerts will appear here automatically."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.notifications', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Refresh</a>
                <a href="{{ route('property.communications.messages', absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Open communications logs</a>
            </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.notifications', [
            'filters' => $filters ?? [],
            'perPage' => $perPage ?? 25,
        ])
    </x-slot>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-gray-800/80 shadow-sm">
        <form method="post" action="{{ route('property.notifications.bulk', absolute: false) }}">
            @csrf
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center gap-2">
                <select name="bulk_action" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" required>
                    <option value="">Bulk action...</option>
                    <option value="mark_read">Mark as read</option>
                    <option value="mark_unread">Mark as unread</option>
                </select>
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Apply to selected</button>
                @error('bulk_action')
                    <span class="text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" onclick="document.querySelectorAll('.notif-pick').forEach(el => el.checked = this.checked)" />
                            </th>
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Read</th>
                            <th class="px-4 py-3">Channel</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($logs ?? collect()) as $log)
                            <tr class="border-t border-slate-100 dark:border-slate-700/80">
                                <td class="px-4 py-3 align-top">
                                    <input class="notif-pick" type="checkbox" name="selected_ids[]" value="{{ $log->id }}" />
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">
                                    @if (($readLookup ?? collect())->has((int) $log->id))
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">READ</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">UNREAD</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ strtoupper((string) $log->channel) }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ strtoupper((string) ($log->delivery_status ?? 'unknown')) }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $log->subject ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200 max-w-md">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($log->body ?? '')), 120) }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">No notifications found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        @if (method_exists($logs, 'links'))
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-property.workspace>
