<x-property-layout>
    <x-slot name="header">Communications — Schedules</x-slot>

    <x-property.page
        title="Message schedules"
        subtitle="Turn each automatic send on or off. Times follow the server timezone ({{ $timezone }})."
    >
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
        @endif

        @if ($envForcesOff)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                The server setting <code>PROPERTY_WORKFLOW_AUTOMATION_ENABLED=false</code> is forcing every job off. Remove that line to use the switches below.
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Job</th>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">What it sends</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastGroup = null; @endphp
                    @foreach ($jobs as $job)
                        @if ($lastGroup !== $job['group'])
                            @php $lastGroup = $job['group']; @endphp
                            <tr class="bg-slate-100">
                                <td colspan="5" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $job['group'] }}</td>
                            </tr>
                        @endif
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3 align-top">
                                <div class="font-semibold text-slate-900">{{ $job['label'] }}</div>
                                <div class="mt-0.5 font-mono text-[11px] text-slate-500">{{ $job['command'] }}</div>
                            </td>
                            <td class="px-4 py-3 align-top whitespace-nowrap text-slate-700">{{ $job['when'] }}</td>
                            <td class="px-4 py-3 align-top text-slate-600">{{ $job['sends'] }}</td>
                            <td class="px-4 py-3 align-top">
                                @if ($job['enabled'] && ! $envForcesOff)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">On</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <form method="post" action="{{ route('property.communications.schedules.update', false) }}">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $job['key'] }}">
                                    <input type="hidden" name="enabled" value="{{ $job['enabled'] ? '0' : '1' }}">
                                    <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white {{ $job['enabled'] ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }}" @disabled($envForcesOff)>
                                        {{ $job['enabled'] ? 'Turn off' : 'Turn on' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-slate-500">Turning a job off stops the next scheduled run. Messages already queued still need the queue worker. Invoice creation and invoice delivery are separate switches.</p>
    </x-property.page>
</x-property-layout>
