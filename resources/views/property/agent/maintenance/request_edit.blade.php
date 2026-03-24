<x-property.workspace
    title="Edit maintenance request"
    subtitle="Update ticket details, urgency, and status for an existing maintenance request."
    back-route="property.maintenance.requests"
    :stats="[
        ['label' => 'Request ID', 'value' => '#'.$requestItem->id, 'hint' => $requestItem->created_at->format('Y-m-d')],
        ['label' => 'Reported by', 'value' => $requestItem->reportedBy?->name ?? '—', 'hint' => 'Request owner'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.maintenance.requests.update', $requestItem) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Request details</h3>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
            <select name="property_unit_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select…</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" @selected((string) old('property_unit_id', $requestItem->property_unit_id) === (string) $u->id)>{{ $u->property->name }} / {{ $u->label }}</option>
                @endforeach
            </select>
            @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
            <input type="text" name="category" value="{{ old('category', $requestItem->category) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Urgency</label>
                <select name="urgency" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="normal" @selected(old('urgency', $requestItem->urgency) === 'normal')>Normal</option>
                    <option value="urgent" @selected(old('urgency', $requestItem->urgency) === 'urgent')>Urgent</option>
                    <option value="emergency" @selected(old('urgency', $requestItem->urgency) === 'emergency')>Emergency</option>
                </select>
                @error('urgency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="open" @selected(old('status', $requestItem->status) === 'open')>Open</option>
                    <option value="in_progress" @selected(old('status', $requestItem->status) === 'in_progress')>In progress</option>
                    <option value="done" @selected(old('status', $requestItem->status) === 'done')>Done</option>
                    <option value="closed" @selected(old('status', $requestItem->status) === 'closed')>Closed</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
            <textarea name="description" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('description', $requestItem->description) }}</textarea>
            @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.maintenance.requests') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
</x-property.workspace>

