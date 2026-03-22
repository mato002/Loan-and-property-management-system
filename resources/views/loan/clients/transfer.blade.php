<x-loan-layout>
    <x-loan.page
        title="Transfer clients"
        subtitle="Reassign branch and relationship officer. A transfer record is kept for audit."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                View clients
            </a>
        </x-slot>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8">
                <h2 class="text-sm font-semibold text-slate-700 mb-4">New transfer</h2>
                <form method="post" action="{{ route('loan.clients.transfer.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="loan_client_id" value="Client" />
                        <select id="loan_client_id" name="loan_client_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select client —</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}" @selected(old('loan_client_id') == $c->id)>{{ $c->client_number }} — {{ $c->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('loan_client_id')" />
                    </div>
                    <div>
                        <x-input-label for="to_branch" value="To branch" />
                        <x-text-input id="to_branch" name="to_branch" type="text" class="mt-1 block w-full" :value="old('to_branch')" />
                        <x-input-error class="mt-2" :messages="$errors->get('to_branch')" />
                    </div>
                    <div>
                        <x-input-label for="to_employee_id" value="To officer" />
                        <select id="to_employee_id" name="to_employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Keep current —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('to_employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('to_employee_id')" />
                    </div>
                    <div>
                        <x-input-label for="reason" value="Reason (optional)" />
                        <textarea id="reason" name="reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('reason') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                    </div>
                    <x-primary-button type="submit">{{ __('Apply transfer') }}</x-primary-button>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Recent transfers</h2>
                    <p class="text-xs text-slate-500 mt-1">Last 25 movements</p>
                </div>
                <ul class="divide-y divide-slate-100 max-h-[480px] overflow-y-auto">
                    @forelse ($recentTransfers as $t)
                        <li class="px-5 py-3 text-xs text-slate-600">
                            <p class="font-medium text-slate-800">{{ $t->loanClient?->full_name ?? 'Client' }} · {{ $t->loanClient?->client_number }}</p>
                            <p class="mt-1">{{ $t->created_at->format('M j, Y H:i') }} · {{ $t->transferredByUser?->name }}</p>
                            <p>Branch: {{ $t->from_branch ?? '—' }} → {{ $t->to_branch ?? '—' }}</p>
                            <p>Officer: {{ $t->fromEmployee?->full_name ?? '—' }} → {{ $t->toEmployee?->full_name ?? '—' }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-slate-500 text-sm">No transfers yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
