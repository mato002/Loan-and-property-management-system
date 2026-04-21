<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to dashboard
            </a>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Month</label>
                    <input type="month" name="month" value="{{ $month }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Load</button>
                <p class="text-xs text-slate-500 ml-2">Editing targets for {{ $monthLabel }}</p>
            </div>
        </form>

        <form method="post" action="{{ route('loan.dashboard.performance_targets.update') }}" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Monthly target overrides</h2>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-xs font-bold text-white hover:bg-[#264040]">Save targets</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Staff</th>
                            <th class="px-3 py-3 text-right">New</th>
                            <th class="px-3 py-3 text-right">Repeat</th>
                            <th class="px-3 py-3 text-right">Arrears</th>
                            <th class="px-3 py-3 text-right">Performing</th>
                            <th class="px-3 py-3 text-right">Gross disb.</th>
                            <th class="px-3 py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $i => $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900 whitespace-nowrap">
                                    {{ $row['staff_name'] }}
                                    <input type="hidden" name="targets[{{ $i }}][employee_id]" value="{{ $row['employee_id'] }}">
                                </td>
                                <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="targets[{{ $i }}][new_target]" value="{{ $row['new_target'] }}" class="w-24 rounded-md border-slate-200 text-sm text-right"></td>
                                <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="targets[{{ $i }}][repeat_target]" value="{{ $row['repeat_target'] }}" class="w-24 rounded-md border-slate-200 text-sm text-right"></td>
                                <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="targets[{{ $i }}][arrears_target]" value="{{ $row['arrears_target'] }}" class="w-28 rounded-md border-slate-200 text-sm text-right"></td>
                                <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="targets[{{ $i }}][performing_target]" value="{{ $row['performing_target'] }}" class="w-28 rounded-md border-slate-200 text-sm text-right"></td>
                                <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="targets[{{ $i }}][gross_target]" value="{{ $row['gross_target'] }}" class="w-32 rounded-md border-slate-200 text-sm text-right"></td>
                                <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="targets[{{ $i }}][revenue_target]" value="{{ $row['revenue_target'] }}" class="w-32 rounded-md border-slate-200 text-sm text-right"></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-slate-500">No employees found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </x-loan.page>
</x-loan-layout>
