<x-property.crud-shell
    :in-property-form-modal="$inPropertyFormModal ?? false"
    title="New leave request"
    subtitle="Submit leave for a property employee."
    back-route="property.hr.leaves.index"
    :stats="[]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.hr.leaves.store', absolute: false) }}" class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-4 max-w-2xl">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Employee</label>
            <select name="employee_id" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select employee…</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) old('employee_id', $defaultEmployeeId ?? 0) === (int) $employee->id)>{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                @endforeach
            </select>
            @error('employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Leave type</label>
            <select name="leave_type" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                @foreach ($leaveTypes as $type)
                    <option value="{{ $type }}" @selected(old('leave_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @error('leave_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Start date</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('start_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">End date</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('end_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
            @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Submit leave</button>
            <a href="{{ route('property.hr.leaves.index', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</x-property.crud-shell>
