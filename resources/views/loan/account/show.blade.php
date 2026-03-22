<x-loan-layout>
    <x-loan.page
        title="My account"
        subtitle="Signed-in user summary and shortcuts to personal loan-office tools."
    >
        <x-slot name="actions">
            <a href="{{ route('profile.edit') }}#update-profile" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Edit profile
            </a>
        </x-slot>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-800 mb-4">Your details</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ Auth::user()->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</dt>
                            <dd class="mt-1 font-medium text-slate-900 break-all">{{ Auth::user()->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Member since</dt>
                            <dd class="mt-1 text-slate-700 tabular-nums">{{ Auth::user()->created_at?->format('M j, Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Email status</dt>
                            <dd class="mt-1">
                                @if (Auth::user()->hasVerifiedEmail())
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-800 border border-emerald-100 px-2.5 py-0.5 text-xs font-semibold">Verified</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-900 border border-amber-100 px-2.5 py-0.5 text-xs font-semibold">Pending verification</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3">Quick links</h2>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        <li>
                            <a href="{{ route('loan.employees.workplan') }}" class="text-indigo-600 font-medium hover:underline">My workplan</a>
                        </li>
                        <li>
                            <a href="{{ route('loan.account.salary_advance') }}" class="text-indigo-600 font-medium hover:underline">Salary advance</a>
                        </li>
                        <li>
                            <a href="{{ route('loan.employees.staff_loans') }}" class="text-indigo-600 font-medium hover:underline">My staff loans</a>
                        </li>
                        <li>
                            <a href="{{ route('loan.account.approval_requests') }}" class="text-indigo-600 font-medium hover:underline">Approval requests</a>
                        </li>
                        <li>
                            <a href="{{ route('profile.edit') }}#update-profile" class="text-indigo-600 font-medium hover:underline">Update profile &amp; password</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="space-y-4">
                <div class="bg-[#2f4f4f] text-white rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-white/90 mb-3">This week</h2>
                    <p class="text-sm text-[#d4e4e3] leading-relaxed">
                        Connect HR, payroll, and committee data here when APIs are available. Until then, use the sidebar under <span class="font-semibold text-white">My Account</span> for navigation.
                    </p>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
