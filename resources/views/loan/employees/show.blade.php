<x-loan-layout>
    <x-loan.page
        title="Employee profile"
        subtitle="Review profile, assignment, and linked login account."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
            <a href="{{ route('loan.employees.edit', $employee) }}" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                Edit employee
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Employee number</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->employee_number }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Name</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->full_name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Department</p>
                    <p class="mt-1 text-sm text-slate-800">{{ $employee->department ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Job title</p>
                    <p class="mt-1 text-sm text-slate-800">{{ $employee->job_title ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Branch</p>
                    <p class="mt-1 text-sm text-slate-800">{{ $employee->branch ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Hire date</p>
                    <p class="mt-1 text-sm text-slate-800">{{ optional($employee->hire_date)->format('Y-m-d') ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Work email</p>
                    <p class="mt-1 text-sm text-slate-800 break-all">{{ $employee->email ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                    <p class="mt-1 text-sm text-slate-800">{{ $employee->phone ?: '—' }}</p>
                </div>
            </div>

            <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-semibold text-slate-800">Linked login account</h2>
                @if ($linkedUser)
                    <p class="mt-2 text-sm text-slate-700">
                        {{ $linkedUser->email }} · role:
                        <span class="font-semibold">
                            {{ optional($linkedUser->activeLoanAccessRole())->name ?: ($linkedUser->effectiveLoanRole() ?: $linkedUser->loan_role ?: 'user') }}
                        </span>
                    </p>
                    <form method="post" action="{{ route('loan.employees.resend_login', $employee) }}" class="mt-3" data-swal-confirm="Resend login credentials to this employee email?">
                        @csrf
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Resend login credentials</button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-slate-600">No linked user account found for this employee email.</p>
                @endif
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
