<x-property-layout>
    <x-slot name="header">Notifications</x-slot>

    <x-property.page
        title="Notifications"
        subtitle="Rent received, issues escalated, lease expiries — short and trustworthy."
    >
        <ul class="rounded-2xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-gray-800/70 overflow-hidden shadow-sm">
            @foreach (['Rent received — Unit —', 'Maintenance update — Job #—', 'Lease expiring in 60 days —'] as $line)
                <li class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300 flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
                    {{ $line }} <span class="text-xs text-slate-400">(sample)</span>
                </li>
            @endforeach
        </ul>
    </x-property.page>
</x-property-layout>
