<x-loan-layout>
    <x-loan.page title="Edit payroll period" subtitle="">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.show', $period) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to period</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            <form method="post" action="{{ route('loan.accounting.payroll.update', $period) }}">
                @csrf @method('patch')
                <div><label class="text-xs font-semibold text-slate-600">Start</label><input name="period_start" type="date" value="{{ old('period_start', $period->period_start->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">End</label><input name="period_end" type="date" value="{{ old('period_end', $period->period_end->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Label</label><input name="label" value="{{ old('label', $period->label) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="draft" @selected(old('status', $period->status) === 'draft')>Draft</option>
                        <option value="processed" @selected(old('status', $period->status) === 'processed')>Processed</option>
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $period->notes) }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
