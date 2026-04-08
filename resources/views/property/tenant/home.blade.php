<x-property-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="bg-teal-100 rounded-lg p-2">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-gray-900">Welcome back!</h1>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Active
                </span>
            </div>
        </div>
    </x-slot>

    <x-property.page
        title="Home"
        subtitle="Current balance, next due date, and quick actions — keep it thumb-friendly."
    >
        <x-property.module-status label="Tenant dashboard" class="mb-6" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-500">Outstanding balance</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ \App\Services\Property\PropertyMoney::kes((float) ($balanceAmount ?? 0)) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-500">Next due</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $nextDue ?? '—' }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-500">Rent / Water balances</p>
                <p class="text-sm font-semibold text-gray-900 mt-1">
                    {{ \App\Services\Property\PropertyMoney::kes((float) ($rentBalanceAmount ?? 0)) }}
                    <span class="text-gray-400 font-normal">/</span>
                    {{ \App\Services\Property\PropertyMoney::kes((float) ($waterBalanceAmount ?? 0)) }}
                </p>
            </div>
        </div>

        @php
            $statusCounts = $paymentStatusCounts ?? ['completed' => 0, 'pending' => 0, 'failed' => 0];
            $statusMax = max(1, (int) max($statusCounts));
            $channelCounts = $paymentChannelCounts ?? [];
            $channelMax = max(1, (int) (count($channelCounts) ? max($channelCounts) : 1));
            $trend = $monthlyPaidTrend ?? [];
            $trendMax = max(1, (float) (collect($trend)->max('amount') ?? 1));
        @endphp

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-4 xl:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Payment insights</h3>
                    <a href="{{ route('property.tenant.payments.history') }}" class="text-xs text-teal-600 hover:text-teal-700 font-medium">Full history →</a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">By status</p>
                        @foreach (['completed' => 'bg-emerald-500', 'pending' => 'bg-amber-500', 'failed' => 'bg-red-500'] as $key => $barClass)
                            @php
                                $v = (int) ($statusCounts[$key] ?? 0);
                                $w = (int) round(($v / $statusMax) * 100);
                            @endphp
                            <div class="mb-2">
                                <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                    <span>{{ ucfirst($key) }}</span>
                                    <span>{{ $v }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full {{ $barClass }}" style="width: {{ $w }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">By channel</p>
                        @forelse ($channelCounts as $label => $count)
                            @php $w = (int) round((((int) $count) / $channelMax) * 100); @endphp
                            <div class="mb-2">
                                <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                    <span>{{ $label }}</span>
                                    <span>{{ (int) $count }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-teal-500" style="width: {{ $w }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500">No payment channel data yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Loan eligibility</p>
                <p class="mt-2 text-lg font-semibold {{ ($loanEligible ?? false) ? 'text-emerald-700' : 'text-red-600' }}">
                    {{ ($loanEligible ?? false) ? 'Eligible' : 'Blocked by arrears' }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ ($loanEligible ?? false) ? 'You can submit a loan request from Loans.' : 'Clear arrears first to unlock loan requests.' }}
                </p>
                <a href="{{ route('property.tenant.loans') }}" class="inline-flex mt-3 rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-700">
                    Open loans
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-8">
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">6-month payment trend</h3>
            @if (count($trend))
                <div class="grid grid-cols-6 gap-3 items-end h-36">
                    @foreach ($trend as $m)
                        @php $h = (int) max(6, round((($m['amount'] ?? 0) / $trendMax) * 100)); @endphp
                        <div class="flex flex-col items-center justify-end h-full">
                            <div class="w-full rounded-t-md bg-teal-500/85 hover:bg-teal-600 transition-colors" style="height: {{ $h }}%"></div>
                            <p class="mt-2 text-[11px] text-gray-500">{{ $m['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">No completed payment trend yet.</p>
            @endif
        </div>

        <!-- Quick Actions Grid -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Quick actions</h3>
                <span class="text-xs text-gray-400">Thumb-friendly</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a href="{{ route('property.tenant.payments.pay') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white py-5 text-sm font-semibold text-teal-600 hover:bg-gradient-to-br hover:from-teal-50 hover:to-teal-50/50 hover:border-teal-200 transition-all duration-200 shadow-sm hover:shadow-md">
                    <div class="bg-teal-50 rounded-full p-3 group-hover:bg-teal-100 transition-colors">
                        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <span>Pay rent</span>
                    <span class="text-xs text-gray-400 font-normal">M-Pesa STK</span>
                </a>
                
                <a href="{{ route('property.tenant.lease') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white py-5 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 shadow-sm hover:shadow-md">
                    <div class="bg-gray-50 rounded-full p-3 group-hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span>Lease</span>
                    <span class="text-xs text-gray-400 font-normal">View documents</span>
                </a>
                
                <a href="{{ route('property.tenant.maintenance.report') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white py-5 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 shadow-sm hover:shadow-md">
                    <div class="bg-gray-50 rounded-full p-3 group-hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <span>Maintenance</span>
                    <span class="text-xs text-gray-400 font-normal">Report issue</span>
                </a>
                
                <a href="{{ route('property.tenant.requests') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white py-5 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 shadow-sm hover:shadow-md">
                    <div class="bg-gray-50 rounded-full p-3 group-hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <span>Requests</span>
                    <span class="text-xs text-gray-400 font-normal">Submit request</span>
                </a>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-sm font-semibold text-gray-900">Recent activity</h3>
                    </div>
                    <a href="{{ route('property.tenant.payments.history') }}" class="text-xs text-teal-600 hover:text-teal-700 font-medium">View all →</a>
                </div>
            </div>
            <div class="divide-y divide-gray-100">
                @if ($lastCompletedPayment)
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center space-x-3">
                            <div class="bg-green-50 rounded-lg p-2">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Last completed payment</p>
                                <p class="text-xs text-gray-500">Ref {{ $lastCompletedPayment->external_ref ?? '—' }} • {{ strtoupper($lastCompletedPayment->channel) }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ \App\Services\Property\PropertyMoney::kes((float) $lastCompletedPayment->amount) }}</p>
                            <p class="text-xs text-gray-400">{{ $lastCompletedPayment->paid_at?->format('M d, Y') ?? '—' }}</p>
                        </div>
                    </div>
                @endif
                @forelse (($recentPayments ?? collect())->take(3) as $p)
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-50 rounded-lg p-2">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ ucfirst($p->status) }} payment #{{ $p->id }}</p>
                                <p class="text-xs text-gray-500">{{ strtoupper($p->channel) }} @if($p->external_ref) • {{ $p->external_ref }} @endif</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ \App\Services\Property\PropertyMoney::kes((float) $p->amount) }}</p>
                            <p class="text-xs text-gray-400">{{ ($p->paid_at ?? $p->created_at)?->diffForHumans() ?? '—' }}</p>
                        </div>
                    </div>
                @empty
                    @if (! $lastCompletedPayment)
                        <div class="px-5 py-6 text-sm text-gray-500">No recent payment activity yet.</div>
                    @endif
                @endforelse
            </div>
        </div>
    </x-property.page>
</x-property-layout>