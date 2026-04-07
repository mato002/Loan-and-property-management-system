<x-property-layout>
    <x-slot name="header">Maintenance</x-slot>

    <x-property.page
        title="Maintenance"
        subtitle="Report with photos, track status, view history."
    >
        <a href="{{ route('property.tenant.maintenance.report') }}" class="block w-full rounded-2xl bg-teal-600 text-white text-center py-4 text-sm font-semibold shadow-md hover:bg-teal-700 transition-colors">
            Report an issue
        </a>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4 mt-4">
            <p class="text-xs font-medium uppercase text-slate-500">Active requests</p>
            @if (($requests ?? collect())->isEmpty())
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">None yet — your open jobs will list here.</p>
            @else
                <div class="mt-3 space-y-2">
                    @foreach ($requests as $r)
                        @php
                            $status = (string) ($r->status ?? 'open');
                            $badge = match ($status) {
                                'done', 'closed' => 'bg-emerald-100 text-emerald-700',
                                'in_progress' => 'bg-amber-100 text-amber-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                            $job = $r->jobs->sortByDesc('id')->first();
                        @endphp
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $r->unit->property->name ?? 'Property' }}/{{ $r->unit->label ?? 'Unit' }} - {{ ucfirst((string) $r->category) }}
                                </p>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">
                                    {{ str_replace('_', ' ', ucfirst($status)) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit((string) $r->description, 120) }}</p>
                            <p class="mt-2 text-xs text-slate-500">
                                Last update: {{ optional($r->updated_at)->format('Y-m-d H:i') }}
                                @if ($job)
                                    · Job #{{ $job->id }} {{ str_replace('_', ' ', $job->status) }}
                                    @if ($job->vendor)
                                        · Vendor: {{ $job->vendor->name }}
                                    @endif
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-property.page>
</x-property-layout>
