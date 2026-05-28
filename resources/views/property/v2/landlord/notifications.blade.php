<x-property-layout>
    <x-slot name="header">Notifications</x-slot>

    <x-property.page
        title="Notifications"
        subtitle="Derived from maintenance, leases, payments, and overdue invoices on your properties."
    >
        <form method="post" action="{{ route('property.landlord.notifications.preferences.store') }}" class="mb-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4" data-swal-confirm="Save notification preferences?">
            @csrf
            <p class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Alert preferences</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_rent_collected" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_rent_collected'] ?? true)> Rent collected</label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_overdue" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_overdue'] ?? true)> Overdue invoices</label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_maintenance" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_maintenance'] ?? true)> Maintenance updates</label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_lease_expiry" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_lease_expiry'] ?? true)> Lease expiry</label>
            </div>
            <button type="submit" class="mt-3 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Save preferences</button>
        </form>

        @if (count($notifications ?? []) === 0)
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-8 text-center text-sm text-slate-600 dark:text-slate-300">
                <p>Nothing to show yet — when rent posts, work orders move, or leases approach expiry, they will appear here.</p>
            </div>
        @else
            <ul class="rounded-2xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-gray-800/70 overflow-hidden shadow-sm">
                @foreach ($notifications as $n)
                    @php
                        $dot = match ($n['tone'] ?? 'slate') {
                            'emerald' => 'bg-emerald-500',
                            'amber' => 'bg-amber-500',
                            'rose' => 'bg-rose-500',
                            default => 'bg-slate-400',
                        };
                    @endphp
                    <li class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300 flex gap-3">
                        <span class="w-2 h-2 rounded-full shrink-0 mt-1.5 {{ $dot }}" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $n['title'] }}</p>
                            <p class="mt-0.5 text-slate-600 dark:text-slate-400">{{ $n['body'] }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $n['at']->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-property.page>
</x-property-layout>
