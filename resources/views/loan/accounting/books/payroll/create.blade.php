<x-loan-layout>
    <x-loan.page title="New payroll period" subtitle="">
        <x-slot name="actions"><a href="{{ route('loan.accounting.payroll.hub') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a></x-slot>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            <form method="post" action="{{ route('loan.accounting.payroll.store') }}">
                @csrf
                <div><label class="text-xs font-semibold text-slate-600">Start</label><input name="period_start" type="date" value="{{ old('period_start', request('period_start')) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">End</label><input name="period_end" type="date" value="{{ old('period_end', request('period_end')) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Label</label><input name="label" value="{{ old('label', request('label')) }}" placeholder="e.g. March 2026" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Create</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
