<x-property-layout>
    <x-slot name="header">Notifications</x-slot>

    <x-property.page
        title="Notifications"
        subtitle="Maintenance progress, reminders, confirmations, and announcements."
    >
        <div class="mb-3 flex justify-end">
            <form method="post" action="{{ route('property.tenant.notifications.read_all') }}">
                @csrf
                <button type="submit" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Mark all as read
                </button>
            </form>
        </div>

        @if (($logs ?? collect())->isEmpty())
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 px-4 py-6 text-sm text-slate-600 dark:text-slate-300">
                No notifications yet.
            </div>
        @else
            <ul class="space-y-2">
                @foreach ($logs as $log)
                    @php $isRead = isset($readMap) && $readMap->has($log->id); @endphp
                    <li class="rounded-xl border {{ $isRead ? 'border-slate-200' : 'border-emerald-300' }} dark:border-slate-700 bg-white dark:bg-gray-800/70 px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $log->subject ?: 'Notification' }}</p>
                            <div class="flex items-center gap-2">
                                @if (! $isRead)
                                    <form method="post" action="{{ route('property.tenant.notifications.read_one', $log) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-emerald-300 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Mark read</button>
                                    </form>
                                @else
                                    <span class="text-xs font-semibold text-slate-400">Read</span>
                                @endif
                                <span class="text-xs text-slate-400">{{ optional($log->created_at)->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $log->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-property.page>
</x-property-layout>
