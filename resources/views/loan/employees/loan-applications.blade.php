<x-loan-layout>
    <x-loan.page
        title="Employee loan applications"
        subtitle="Internal staff loan pipeline — create applications and move stage or status."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.loan_applications.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New staff application
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Pipeline</h2>
                <p class="text-xs text-slate-500">{{ $applications->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Ref</th>
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Amount (Ksh)</th>
                            <th class="px-5 py-3">Submitted</th>
                            <th class="px-5 py-3 min-w-[220px]">Stage &amp; status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($applications as $app)
                            <tr class="hover:bg-slate-50/80 align-top">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $app->reference ?? '—' }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $app->employee->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $app->product }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ number_format($app->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums whitespace-nowrap">{{ $app->created_at->format('Y-m-d') }}</td>
                                <td class="px-5 py-3">
                                    <form method="post" action="{{ route('loan.employees.loan_applications.update', $app) }}" class="flex flex-col sm:flex-row flex-wrap gap-2 items-stretch sm:items-end">
                                        @csrf
                                        @method('patch')
                                        <div class="flex-1 min-w-[120px]">
                                            <label class="sr-only" for="stage-{{ $app->id }}">Stage</label>
                                            <input id="stage-{{ $app->id }}" type="text" name="stage" value="{{ old('stage', $app->stage) }}" class="w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                                        </div>
                                        <div>
                                            <label class="sr-only" for="status-{{ $app->id }}">Status</label>
                                            <select id="status-{{ $app->id }}" name="status" class="rounded-md border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-full sm:w-auto">
                                                @foreach (['pending', 'approved', 'declined', 'disbursed'] as $st)
                                                    <option value="{{ $st }}" @selected(old('status', $app->status) === $st)>{{ ucfirst($st) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No applications. <a href="{{ route('loan.employees.loan_applications.create') }}" class="text-indigo-600 font-medium hover:underline">Create one</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $applications->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
