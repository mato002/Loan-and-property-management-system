<x-property.workspace
    title="Job tracking"
    subtitle="Work orders linked to requests and vendors."
    back-route="property.maintenance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No jobs"
    empty-hint="Create a job from an open request; mark done to stamp completion time."
>
    <x-slot name="above">
        <form method="post" action="{{ route('property.maintenance.jobs.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New job</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Request</label>
                <select name="pm_maintenance_request_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($requests as $r)
                        <option value="{{ $r->id }}" @selected(old('pm_maintenance_request_id') == $r->id)>#{{ $r->id }} · {{ $r->unit->property->name }}/{{ $r->unit->label }} · {{ $r->category }}</option>
                    @endforeach
                </select>
                @error('pm_maintenance_request_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Vendor</label>
                    <select name="pm_vendor_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">—</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}" @selected(old('pm_vendor_id') == $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                    @error('pm_vendor_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Quote (KES)</label>
                    <input type="number" name="quote_amount" value="{{ old('quote_amount') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('quote_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="quoted" @selected(old('status', 'quoted') === 'quoted')>Quoted</option>
                        <option value="approved" @selected(old('status') === 'approved')>Approved</option>
                        <option value="in_progress" @selected(old('status') === 'in_progress')>In progress</option>
                        <option value="done" @selected(old('status') === 'done')>Done</option>
                        <option value="cancelled" @selected(old('status') === 'cancelled')>Cancelled</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save job</button>
        </form>
    </x-slot>
</x-property.workspace>
