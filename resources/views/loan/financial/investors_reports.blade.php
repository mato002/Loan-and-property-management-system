<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.investors_list') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Investors list
            </a>
        </x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Principal outstanding</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums mt-2">{{ number_format($principalOutstanding, 2) }}</p>
                <p class="text-xs text-slate-500 mt-1">Sum of committed amounts</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Accrued interest</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums mt-2">{{ number_format($accruedInterest, 2) }}</p>
                <p class="text-xs text-slate-500 mt-1">Stored on investor records</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Maturing (30d)</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums mt-2">{{ $maturing30d }}</p>
                <p class="text-xs text-slate-500 mt-1">Investors with maturity date set</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Investors</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums mt-2">{{ $investorCount }}</p>
                <p class="text-xs text-slate-500 mt-1">Total headcount</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Exports</h2>
                <p class="text-xs text-slate-500 mt-0.5">Downloads open in the browser as CSV.</p>
            </div>
            <div class="px-5 py-6 flex flex-wrap gap-3">
                <a href="{{ route('loan.financial.investors_reports.export.statement') }}" data-turbo="false" class="inline-flex items-center justify-center rounded-lg border border-[#2f4f4f] bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040] transition-colors">
                    Investor statement (CSV)
                </a>
                <a href="{{ route('loan.financial.investors_reports.export.maturity') }}" data-turbo="false" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 transition-colors">
                    Maturity schedule (CSV)
                </a>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
