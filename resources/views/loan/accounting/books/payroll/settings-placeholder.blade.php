<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$intro">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.hub') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payroll home</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="max-w-xl rounded-xl border border-amber-200 bg-amber-50/80 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold text-amber-900">Coming soon</p>
            <p class="mt-2 text-amber-900/90 leading-relaxed">This screen will hold configuration forms and rates. Payroll periods and payslips are available from the payroll home.</p>
        </div>
    </x-loan.page>
</x-loan-layout>
