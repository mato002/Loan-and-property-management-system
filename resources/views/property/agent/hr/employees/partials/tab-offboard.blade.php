@php
    $canManage = $canManage ?? false;
    $exitReasons = $exitReasons ?? \App\Models\Employee::EXIT_REASONS;
@endphp

<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm max-w-3xl">
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Offboard employee</h3>
    <p class="mt-1 text-xs text-slate-500">One action records the last working day, revokes the portal login, clears roles and permission overrides, and detaches every assigned property.</p>

    @if ($employee->isOffboarded())
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-950 space-y-1">
            <p><span class="font-medium">Last day:</span> {{ $employee->exit_date?->format('Y-m-d') ?? '—' }}</p>
            <p><span class="font-medium">Reason:</span> {{ $exitReasons[$employee->exit_reason] ?? ($employee->exit_reason ?: '—') }}</p>
            @if ($employee->offboarding_notes)
                <p><span class="font-medium">Notes:</span> {{ $employee->offboarding_notes }}</p>
            @endif
            @if ($canManage)
                <form method="post" action="{{ route('property.hr.employees.status', $employee) }}" class="pt-2" data-turbo-frame="_top" data-swal-title="Re-activate employee?" data-swal-confirm="Restores them as active. Portal access is not restored automatically." data-swal-confirm-text="Re-activate">
                    @csrf
                    <input type="hidden" name="employment_status" value="active" />
                    <button type="submit" class="inline-flex min-h-[40px] items-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Re-activate</button>
                </form>
            @endif
        </div>
    @elseif ($canManage)
        <form method="post" action="{{ route('property.hr.employees.offboard', $employee) }}" class="mt-4 grid gap-3 sm:grid-cols-2" data-turbo-frame="_top" data-swal-title="Offboard this employee?" data-swal-confirm="This removes their portal login, permissions, and property assignments." data-swal-confirm-text="Offboard">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600">Last working day <span class="text-rose-600">*</span></label>
                <input type="date" name="exit_date" required value="{{ old('exit_date', now()->toDateString()) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('exit_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Reason <span class="text-rose-600">*</span></label>
                <select name="exit_reason" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach ($exitReasons as $value => $label)
                        <option value="{{ $value }}" @selected(old('exit_reason', 'resignation') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('exit_reason')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600">Notes</label>
                <textarea name="offboarding_notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('offboarding_notes') }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex min-h-[40px] items-center rounded-xl bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800">Offboard employee</button>
            </div>
        </form>
    @else
        <p class="mt-4 text-sm text-slate-500">This employee is still employed. Offboarding is available to HR managers.</p>
    @endif
</div>
