<x-property.workspace
    title="Notifications"
    subtitle="In-app alerts from applications, system events, and communication activity."
    back-route="property.dashboard"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
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
</x-property.workspace>
