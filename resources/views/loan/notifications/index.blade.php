<x-loan-layout>
    <x-loan.page
        title="Previous Notifications"
        subtitle="Messages and workflow alerts from your loan workspace."
    >
        <x-slot name="actions">
            <form method="post" action="{{ route('loan.notifications.read_all') }}" data-swal-confirm="Mark all notifications as read?">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                    Mark all as read
                </button>
            </form>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Notifications</h2>
                <p class="text-xs text-slate-500">{{ (int) ($unreadCount ?? 0) }} unread</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($notifications as $item)
                    @php
                        $data = is_array($item->data ?? null) ? $item->data : [];
                        $message = trim((string) ($data['message'] ?? $data['text'] ?? $item->type));
                        $title = trim((string) ($data['title'] ?? 'Notification'));
                        $url = trim((string) ($data['url'] ?? ''));
                    @endphp
                    <li class="px-5 py-4 {{ $item->read_at ? 'bg-white' : 'bg-indigo-50/40' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-wide {{ $item->read_at ? 'text-slate-500' : 'text-indigo-700' }}">{{ $title }}</p>
                                <p class="mt-1 text-sm text-slate-700">{{ $message }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ optional($item->created_at)->format('M j, Y · H:i') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($url !== '')
                                    <a href="{{ $url }}" class="inline-flex items-center rounded-full bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">
                                        Proceed
                                    </a>
                                @endif
                                @if (! $item->read_at)
                                    <form method="post" action="{{ route('loan.notifications.read_one', $item->id) }}" data-swal-confirm="Mark this notification as read?">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-slate-600 hover:text-slate-900">Mark read</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-12 text-center text-slate-500">No notifications yet.</li>
                @endforelse
            </ul>
            @if (method_exists($notifications, 'hasPages') && $notifications->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
