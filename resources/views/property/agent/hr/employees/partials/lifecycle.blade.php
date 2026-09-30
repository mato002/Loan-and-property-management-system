@php
    $checklist = $onboardingChecklist ?? [];
    $doneCount = collect($checklist)->where('done', true)->count();
    $canManage = $canManage ?? false;
    $exitReasons = $exitReasons ?? \App\Models\Employee::EXIT_REASONS;
@endphp

<div class="lg:col-span-2 property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Employment lifecycle</h3>
            <p class="mt-1 text-xs text-slate-500">Onboard → active / leave → offboard. Portal login and field-officer properties are handled at each step.</p>
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
            @if ($employee->isOnboarding()) bg-amber-50 text-amber-900 border border-amber-200
            @elseif ($employee->employmentStatusKey() === 'on_leave') bg-sky-50 text-sky-900 border border-sky-200
            @elseif ($employee->isOffboarded()) bg-rose-50 text-rose-900 border border-rose-200
            @else bg-emerald-50 text-emerald-900 border border-emerald-200
            @endif">{{ $employee->employmentStatusLabel() }}</span>
    </div>

    <div class="mt-4 grid gap-2 sm:grid-cols-4 text-xs">
        @foreach (['onboarding' => '1. Onboard', 'active' => '2. Active', 'on_leave' => '3. Leave', 'terminated' => '4. Offboard'] as $key => $label)
            <div @class([
                'rounded-lg border px-3 py-2',
                $employee->employmentStatusKey() === $key ? 'border-indigo-300 bg-indigo-50 text-indigo-900 font-semibold' : 'border-slate-200 text-slate-500',
            ])>{{ $label }}</div>
        @endforeach
    </div>

    @if ($employee->isOnboarding())
        <div class="mt-4">
            <p class="text-xs font-medium text-slate-600">Onboarding checklist ({{ $doneCount }}/{{ count($checklist) }})</p>
            <ul class="mt-2 grid gap-1 sm:grid-cols-2 text-sm">
                @foreach ($checklist as $item)
                    <li class="{{ $item['done'] ? 'text-emerald-700' : 'text-slate-500' }}">
                        {{ $item['done'] ? '✓' : '○' }} {{ $item['label'] }}
                    </li>
                @endforeach
            </ul>
            @if ($canManage)
                <form method="post" action="{{ route('property.hr.employees.complete_onboarding', $employee) }}" class="mt-3" data-turbo-frame="_top" data-swal-title="Complete onboarding?" data-swal-confirm="Marks this employee as active staff." data-swal-confirm-text="Activate">
                    @csrf
                    <button type="submit" class="inline-flex min-h-[40px] items-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Complete onboarding</button>
                </form>
            @endif
        </div>
    @elseif ($employee->isOffboarded())
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
    @else
        <p class="mt-3 text-sm text-slate-600">
            Hired {{ $employee->hire_date?->format('Y-m-d') ?? '—' }}
            @if ($employee->onboarding_completed_at)
                · onboarded {{ $employee->onboarding_completed_at->format('Y-m-d') }}
            @endif
            @if ($employee->probation_ends_on)
                · probation until {{ $employee->probation_ends_on->format('Y-m-d') }}
            @endif
        </p>
        @if ($canManage && $employee->employmentStatusKey() === 'active')
            <form method="post" action="{{ route('property.hr.employees.status', $employee) }}" class="mt-2 inline-block" data-turbo-frame="_top">
                @csrf
                <input type="hidden" name="employment_status" value="on_leave" />
                <button type="submit" class="text-xs font-semibold text-sky-800 hover:underline">Mark on leave</button>
            </form>
        @endif
        @if ($canManage && $employee->employmentStatusKey() === 'on_leave')
            <form method="post" action="{{ route('property.hr.employees.status', $employee) }}" class="mt-2 inline-block" data-turbo-frame="_top">
                @csrf
                <input type="hidden" name="employment_status" value="active" />
                <button type="submit" class="text-xs font-semibold text-emerald-800 hover:underline">Return from leave</button>
            </form>
        @endif
    @endif

    @if ($canManage && ! $employee->isOffboarded())
        <div id="hr-offboard" class="mt-5 border-t border-slate-200 dark:border-slate-700 pt-4">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Offboard employee</h4>
            <p class="mt-1 text-xs text-slate-500">Records the last working day, revokes portal access, and can unassign field-officer properties.</p>
            <form method="post" action="{{ route('property.hr.employees.offboard', $employee) }}" class="mt-3 grid gap-3 sm:grid-cols-2" data-turbo-frame="_top" data-swal-title="Offboard this employee?" data-swal-confirm="They will be marked offboarded. Portal access is revoked if selected." data-swal-confirm-text="Offboard">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600">Last working day</label>
                    <input type="date" name="exit_date" required value="{{ old('exit_date', now()->toDateString()) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('exit_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Reason</label>
                    <select name="exit_reason" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach ($exitReasons as $value => $label)
                            <option value="{{ $value }}" @selected(old('exit_reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('exit_reason')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600">Notes</label>
                    <textarea name="offboarding_notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('offboarding_notes') }}</textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="revoke_portal" value="0" />
                    <input type="checkbox" name="revoke_portal" value="1" checked class="rounded border-slate-300" />
                    Revoke portal access
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="unassign_properties" value="0" />
                    <input type="checkbox" name="unassign_properties" value="1" checked class="rounded border-slate-300" />
                    Unassign field-officer properties
                </label>
                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex min-h-[40px] items-center rounded-xl bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800">Offboard employee</button>
                </div>
            </form>
        </div>
    @endif
</div>
