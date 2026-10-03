@php
    $checklist = $onboardingChecklist ?? [];
    $doneCount = collect($checklist)->where('done', true)->count();
    $canManage = $canManage ?? false;
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
        <p class="mt-4 text-sm text-slate-600">
            Offboarded {{ $employee->exit_date?->format('Y-m-d') ?? '—' }}.
            Exit details and re-activation are on the Offboard tab.
        </p>
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
</div>
