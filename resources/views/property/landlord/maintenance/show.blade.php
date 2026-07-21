<x-property-layout>
    <x-slot name="header">Maintenance job #{{ $job->id }}</x-slot>

    <x-property.page title="Job #{{ $job->id }}">
        <div class="grid grid-cols-2 gap-2 sm:gap-4 md:grid-cols-2 mb-6">
            <div class="rounded-xl border p-4 space-y-2 text-sm">
                <p><span class="text-slate-500">Property:</span> {{ $job->request?->unit?->property?->name ?? '—' }} / {{ $job->request?->unit?->label ?? '—' }}</p>
                <p><span class="text-slate-500">Category:</span> {{ $job->request?->category ?? '—' }}</p>
                <p><span class="text-slate-500">Status:</span> {{ ucfirst(str_replace('_', ' ', (string) $job->status)) }}</p>
                <p><span class="text-slate-500">Vendor:</span> {{ $job->vendor?->name ?? '—' }}</p>
                <p><span class="text-slate-500">Quote:</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($job->quote_amount ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-slate-500 mb-1">Description</p>
                <p class="text-sm">{{ $job->request?->description ?? '—' }}</p>
                @if ($job->notes)
                    <p class="text-xs text-slate-500 mt-3 mb-1">Job notes</p>
                    <p class="text-sm">{{ $job->notes }}</p>
                @endif
            </div>
        </div>

        @if (in_array($job->status, ['quoted', 'approved'], true))
            <form method="post" action="{{ route('property.landlord.maintenance.jobs.approval', $job) }}" class="rounded-xl border p-4 space-y-3" data-swal-confirm="Confirm decision?">
                @csrf
                <input type="hidden" name="approval_threshold" value="{{ (float) $approvalThreshold }}" />
                <input type="text" name="note" placeholder="Optional note" class="w-full rounded-lg border px-3 py-2 text-sm" />
                <div class="flex gap-2">
                    <button type="submit" name="decision" value="approve" class="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm">Approve</button>
                    <button type="submit" name="decision" value="reject" class="rounded-lg bg-rose-600 text-white px-4 py-2 text-sm">Reject</button>
                </div>
            </form>
        @endif

        <a href="{{ route('property.landlord.maintenance') }}" class="inline-block mt-6 text-sm text-emerald-700 hover:underline">← Back to maintenance</a>
    </x-property.page>
</x-property-layout>
