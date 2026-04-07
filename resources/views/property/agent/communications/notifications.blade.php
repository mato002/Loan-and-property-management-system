<x-property.workspace
    title="Notifications"
    subtitle="In-app alerts from applications, system events, and communication activity."
    back-route="property.dashboard"
    :stats="$stats"
    empty-title="No notifications yet"
    empty-hint="New alerts will appear here automatically."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.notifications', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Refresh</a>
                <a href="{{ route('property.communications.messages', absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Open communications logs</a>
                <a href="{{ route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'csv']), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
                <a href="{{ route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'xls']), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export XLS</a>
                <a href="{{ route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'pdf']), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export PDF</a>
            </div>
        </div>

        <form method="get" action="{{ route('property.notifications') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Subject, body, recipient, error..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                    <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['system', 'email', 'sms'] as $ch)
                            <option value="{{ $ch }}" @selected(($filters['channel'] ?? '') === $ch)>{{ strtoupper($ch) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="sent / failed / queued" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Read</label>
                    <select name="read" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        <option value="unread" @selected(($filters['read'] ?? '') === 'unread')>Unread</option>
                        <option value="read" @selected(($filters['read'] ?? '') === 'read')>Read</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.notifications', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <label class="text-xs text-slate-500 dark:text-slate-400">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-1.5">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
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
