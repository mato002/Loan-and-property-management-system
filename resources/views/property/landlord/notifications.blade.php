<x-property-layout>
    <x-slot name="header">Notifications</x-slot>

    <x-property.page title="Notifications">
        <p class="text-sm text-slate-500 mb-4">
            Activity feed for your portfolio.
            <a href="{{ route('property.landlord.settings.index', ['section' => 'alerts']) }}" data-turbo-frame="property-main" class="text-emerald-700 hover:underline">Manage alert preferences</a>
            in Account.
        </p>

        @if (count($notifications ?? []) === 0)
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-8 text-center text-sm text-slate-600 dark:text-slate-300">
                <p>No notifications.</p>
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
