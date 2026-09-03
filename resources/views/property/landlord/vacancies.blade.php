<x-property-layout>
    <x-slot name="header">Vacant units</x-slot>

    <x-property.page title="Vacant units">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Property</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Target rent</th></tr>
                </thead>
                <tbody>
                    @forelse ($units as $u)
                        @php
                            $rowTone = \App\Support\Property\WorkspaceRowAlert::inferFromRow([(string) ($u['status'] ?? '')]);
                            $cellStyle = \App\Support\Property\WorkspaceRowAlert::cellStyle($rowTone);
                        @endphp
                        <tr class="border-t border-slate-100 {{ \App\Support\Property\WorkspaceRowAlert::trClass($rowTone) }}" @if ($cellStyle !== '') data-row-tone="{{ $rowTone }}" @endif>
                            <td class="px-4 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $u['property'] }}</td>
                            <td class="px-4 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $u['unit'] }}</td>
                            <td class="px-4 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $u['status'] }}</td>
                            <td class="px-4 py-3 tabular-nums" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $u['rent'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No vacant or notice units.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-property.page>
</x-property-layout>
