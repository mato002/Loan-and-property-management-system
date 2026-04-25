<x-property-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-900">Payments</h1>
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
        title="Payments"
        subtitle="KejaPay-style simplicity — pay, history, eTIMS receipts."
    >
        <x-property.module-status label="Payments" class="mb-6" />
        
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Next Payment Due</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ \App\Services\Property\PropertyMoney::kes((float) ($nextDueAmount ?? 0)) }}</p>
                        <p class="text-xs text-gray-400 mt-1">
                            @if (!empty($nextDueDate))
                                Due {{ \Carbon\Carbon::parse($nextDueDate)->diffForHumans() }}
                            @else
                                No unpaid invoice due
                            @endif
                        </p>
                    </div>
                    <div class="bg-orange-50 rounded-full p-3">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Paid (Year)</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ \App\Services\Property\PropertyMoney::kes((float) ($yearPaid ?? 0)) }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ now()->format('Y') }} completed payments</p>
                    </div>
                    <div class="bg-green-50 rounded-full p-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">On-Time Payments</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $onTimePct !== null ? $onTimePct.'%' : '—' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Based on completed allocations</p>
                    </div>
                    <div class="bg-blue-50 rounded-full p-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-500">Rent balance</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ \App\Services\Property\PropertyMoney::kes((float) ($rentBalance ?? 0)) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-500">Water balance</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ \App\Services\Property\PropertyMoney::kes((float) ($waterBalance ?? 0)) }}</p>
            </div>
        </div>

        <x-property.hub-grid :items="[
            [
                'route' => 'property.tenant.payments.pay', 
                'title' => 'Pay bills', 
                'description' => 'Pay rent + water using M-Pesa STK push.',
                'icon' => 'credit-card',
                'gradient' => 'from-emerald-50 to-teal-50',
                'badge' => 'Fast'
            ],
            [
                'route' => 'property.tenant.payments.history', 
                'title' => 'Payment history', 
                'description' => 'Complete transaction log with attempts and settlements.',
                'icon' => 'clock',
                'gradient' => 'from-blue-50 to-indigo-50',
                'badge' => 'Detailed'
            ],
            [
                'route' => 'property.tenant.payments.receipts', 
                'title' => 'Receipts (eTIMS)', 
                'description' => 'Official tax-compliant receipts with QR verification.',
                'icon' => 'document',
                'gradient' => 'from-purple-50 to-pink-50',
                'badge' => 'eTIMS'
            ],
        ]" class="grid grid-cols-1 md:grid-cols-3 gap-6" />

        <!-- Open bills (rent + water) -->
        <div class="mt-8 bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Open bills</h3>
                    <a href="{{ route('property.tenant.payments.pay') }}" class="text-sm text-orange-600 hover:text-orange-700 font-medium">Pay now →</a>
                </div>
                <p class="text-xs text-gray-500 mt-1">Shows each unpaid invoice (rent and water). Payments are automatically allocated to oldest due first.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 whitespace-nowrap">Type</th>
                            <th class="px-6 py-3 whitespace-nowrap">Period</th>
                            <th class="px-6 py-3 whitespace-nowrap">Due</th>
                            <th class="px-6 py-3 whitespace-nowrap">Invoice #</th>
                            <th class="px-6 py-3 whitespace-nowrap text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse (($openInvoices ?? collect()) as $inv)
                            @php
                                $type = (string) ($inv->invoice_type ?? 'rent');
                                $label = $type === 'water' ? 'Water' : ($type === 'mixed' ? 'Mixed' : 'Rent');
                                $bal = max(0, (float) $inv->amount - (float) $inv->amount_paid);
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-900">{{ $label }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $inv->billing_period ?? $inv->issue_date?->format('Y-m') ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 whitespace-nowrap">{{ $inv->due_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-700 whitespace-nowrap">{{ $inv->invoice_no }}</td>
                                <td class="px-6 py-3 tabular-nums text-right font-semibold text-gray-900">{{ \App\Services\Property\PropertyMoney::kes($bal) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-sm text-gray-500">No open bills right now.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Activity Section -->
        <div class="mt-8 bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Recent Activity</h3>
                    <a href="{{ route('property.tenant.payments.history') }}" class="text-sm text-orange-600 hover:text-orange-700 font-medium">View all →</a>
                </div>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse (($recentPayments ?? collect()) as $p)
                    @php
                        $isCompleted = $p->status === \App\Models\PmPayment::STATUS_COMPLETED;
                        $iconBg = $isCompleted ? 'bg-green-50' : ($p->status === \App\Models\PmPayment::STATUS_FAILED ? 'bg-red-50' : 'bg-amber-50');
                        $iconText = $isCompleted ? 'text-green-600' : ($p->status === \App\Models\PmPayment::STATUS_FAILED ? 'text-red-600' : 'text-amber-600');
                    @endphp
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex items-center space-x-3">
                            <div class="{{ $iconBg }} rounded-lg p-2">
                                <svg class="w-5 h-5 {{ $iconText }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ ucfirst($p->status) }} payment</p>
                                <p class="text-xs text-gray-500">{{ strtoupper((string) $p->channel) }} • #{{ $p->id }} @if($p->external_ref) • Ref {{ $p->external_ref }} @endif</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ \App\Services\Property\PropertyMoney::kes((float) $p->amount) }}</p>
                            <p class="text-xs text-gray-400">{{ ($p->paid_at ?? $p->created_at)?->diffForHumans() ?? '—' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-gray-500">No payment activity yet.</div>
                @endforelse
            </div>
        </div>
        
        <!-- Quick Tip -->
        <div class="mt-6 bg-gradient-to-r from-orange-50 to-amber-50 rounded-xl p-4 border border-orange-100">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-orange-900">💡 Quick Tip</p>
                    <p class="text-xs text-orange-700 mt-0.5">Pay your rent 3 days before due date to ensure timely processing and avoid late fees.</p>
                </div>
            </div>
        </div>
    </x-property.page>
</x-property-layout>