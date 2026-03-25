<x-property-layout>
    <x-slot name="header">Home</x-slot>

    <x-property.page
        title="Home"
        subtitle="Current balance, next due date, and quick actions — keep it thumb-friendly."
    >
        <x-property.module-status label="Tenant app" class="mb-4" />

        <div class="rounded-2xl overflow-hidden border border-teal-700/10 bg-gradient-to-br from-teal-600 to-teal-800 text-white shadow-lg shadow-teal-900/20">
            <div class="p-6">
                <p class="text-teal-100 text-sm font-medium">Current balance</p>
                <p class="text-3xl sm:text-4xl font-semibold tabular-nums mt-1 tracking-tight">{{ $balance ?? 'KES 0' }}</p>
                <p class="text-teal-100/90 text-sm mt-3">
                    Next due:
                    <span class="font-semibold text-white">{{ $nextDue ?? '—' }}</span>
                </p>
            </div>
            <div class="grid grid-cols-2 divide-x divide-white/10 border-t border-white/10 bg-white/5">
                <a href="{{ route('property.tenant.payments.pay') }}" class="px-4 py-3 text-sm font-semibold hover:bg-white/10 transition-colors text-center">
                    Pay now
                </a>
                <a href="{{ route('property.tenant.payments.history') }}" class="px-4 py-3 text-sm font-semibold hover:bg-white/10 transition-colors text-center">
                    Payment history
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="{{ route('property.tenant.payments.pay') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 py-5 text-sm font-semibold text-teal-700 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-950/30 transition-colors">
                <span class="text-2xl" aria-hidden="true">💳</span>
                Pay
            </a>
            <a href="{{ route('property.tenant.lease') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 py-5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                <span class="text-2xl" aria-hidden="true">📄</span>
                Lease
            </a>
            <a href="{{ route('property.tenant.maintenance.report') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 py-5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                <span class="text-2xl" aria-hidden="true">🛠️</span>
                Maintenance
            </a>
            <a href="{{ route('property.tenant.requests') }}" class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 py-5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                <span class="text-2xl" aria-hidden="true">📢</span>
                Requests
            </a>
        </div>
    </x-property.page>
</x-property-layout>
