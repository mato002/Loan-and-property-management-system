<x-property-layout>
    <x-slot name="header">Notifications</x-slot>

    <x-property.page
        title="Notifications"
        subtitle="Reminders, confirmations, announcements."
    >
        <ul class="space-y-2">
            @foreach (['Rent reminder — 3 days', 'Payment confirmed', 'Estate announcement'] as $n)
                <li class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    {{ $n }} <span class="text-xs text-slate-400">(sample)</span>
                </li>
            @endforeach
        </ul>
    </x-property.page>
</x-property-layout>
