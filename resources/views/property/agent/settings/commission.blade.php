<x-property-layout>
    <x-slot name="header">Commission settings</x-slot>

    <x-property.page
        title="Commission settings"
        subtitle="Default percentage and internal notes. Detailed fee schedules can live in your contracts until you model them in the database."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.roles') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Property users</a>
            <a href="{{ route('property.settings.commission') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Commission</a>
            <a href="{{ route('property.settings.payments') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payment config</a>
            <a href="{{ route('property.settings.branding') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Branding</a>
            <a href="{{ route('property.settings.rules') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System rules</a>
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup</a>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Default commission</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $defaultPercent !== '' ? $defaultPercent.'%' : 'Not set' }}</p>
                <p class="mt-1 text-xs text-slate-500">Used as the fallback rate when a property-level override is not configured.</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Notes length</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ mb_strlen((string) $notes) }}</p>
                <p class="mt-1 text-xs text-slate-500">Internal memo characters currently saved.</p>
            </div>
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.settings.commission.store') }}" class="max-w-4xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Default commission (%)</label>
                <input type="text" name="commission_default_percent" value="{{ old('commission_default_percent', $defaultPercent) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. 8.5" />
                @error('commission_default_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Per-property commission override (%)</p>
                <p class="mt-1 text-xs text-slate-500">Leave blank to use default commission.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200 dark:border-slate-700">
                                <th class="py-2 pr-4">Property</th>
                                <th class="py-2">Commission %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($properties ?? collect()) as $property)
                                @php
                                    $pid = (string) $property->id;
                                    $saved = $propertyCommissionOverrides[$pid] ?? '';
                                    $value = old('property_commission_overrides.'.$pid, $saved);
                                @endphp
                                <tr class="border-b border-slate-100 dark:border-slate-700/80">
                                    <td class="py-2 pr-4 text-slate-800 dark:text-slate-100">{{ $property->name }}</td>
                                    <td class="py-2">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            name="property_commission_overrides[{{ $property->id }}]"
                                            value="{{ $value }}"
                                            class="w-full max-w-[180px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                            placeholder="Use default"
                                        />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-4 text-sm text-slate-500">No properties found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @error('property_commission_overrides')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
                @error('property_commission_overrides.*')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <textarea name="commission_notes" rows="5" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('commission_notes', $notes) }}</textarea>
                @error('commission_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
        </form>

        <div class="mt-6">
            <a href="{{ route('property.settings.index') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">← Back to settings</a>
        </div>
    </x-property.page>
</x-property-layout>

