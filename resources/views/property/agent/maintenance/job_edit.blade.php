<x-property-layout>
    <x-slot name="header">Edit maintenance job</x-slot>

    <x-property.page
        title="Edit maintenance job"
        subtitle="Update vendor, quote, status, and notes for this job."
    >
        <form method="post" action="{{ route('property.maintenance.jobs.update', $job) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Request</label>
                <select name="pm_maintenance_request_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach ($requests as $r)
                        <option value="{{ $r->id }}" @selected((int) old('pm_maintenance_request_id', $job->pm_maintenance_request_id) === (int) $r->id)>
                            #{{ $r->id }} · {{ $r->unit->property->name }}/{{ $r->unit->label }} · {{ $r->category }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Vendor</label>
                    <select name="pm_vendor_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">—</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}" @selected((string) old('pm_vendor_id', $job->pm_vendor_id) === (string) $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Quote (KES)</label>
                    <input type="number" name="quote_amount" value="{{ old('quote_amount', $job->quote_amount) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach (['quoted' => 'Quoted', 'approved' => 'Approved', 'in_progress' => 'In progress', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $job->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes', $job->notes) }}</textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
                <a href="{{ route('property.maintenance.jobs') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back</a>
            </div>
        </form>
    </x-property.page>
</x-property-layout>
