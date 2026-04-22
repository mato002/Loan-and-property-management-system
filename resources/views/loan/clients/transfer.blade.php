<x-loan-layout>
    <x-loan.page
        title="Transfer clients"
        subtitle="Reassign branch and relationship officer. A transfer record is kept for audit."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                View clients
            </a>
            <a href="{{ route('loan.clients.transfer', array_merge(request()->query(), ['export' => 'csv'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
            <a href="{{ route('loan.clients.transfer', array_merge(request()->query(), ['export' => 'xls'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
            <a href="{{ route('loan.clients.transfer', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
        </x-slot>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6" x-data="transferDraftPreview()">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 xl:col-span-2">
                <h2 class="text-sm font-semibold text-slate-700 mb-1">Transfer active clients</h2>
                <p class="text-xs text-slate-500 mb-4">Move all active clients (optionally including dormant) from one officer to another.</p>
                <form method="post" action="{{ route('loan.clients.transfer.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @csrf
                    <input type="hidden" name="mode" value="bulk_active" />
                    <div>
                        <x-input-label for="from_employee_id" value="Officers with active clients" />
                        <select id="from_employee_id" name="from_employee_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Loan Officer --</option>
                            @foreach ($employees as $employee)
                                @php($clientCount = (int) ($sourceOfficerCounts[$employee->id] ?? 0))
                                @if ($clientCount > 0)
                                    <option value="{{ $employee->id }}" @selected(old('from_employee_id') == $employee->id)>
                                        {{ $employee->full_name }} ({{ $clientCount }} Clients)
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('from_employee_id')" />
                    </div>
                    <div>
                        <x-input-label for="bulk_to_employee_id" value="Transfer to" />
                        <select id="bulk_to_employee_id" name="to_employee_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Loan Officer --</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('to_employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('to_employee_id')" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="include_dormant" value="1" @checked(old('include_dormant')) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Transfer plus Dormant Clients
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="bulk_reason" value="Reason (optional)" />
                        <textarea id="bulk_reason" name="reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('mode') === 'bulk_active' ? old('reason') : '' }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <x-primary-button type="submit">{{ __('Next') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8">
                <h2 class="text-sm font-semibold text-slate-700 mb-4">New transfer</h2>
                <form method="post" action="{{ route('loan.clients.transfer.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="mode" value="single" />
                    <div>
                        <x-input-label for="loan_client_id" value="Client" />
                        <select id="loan_client_id" name="loan_client_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="draft.loanClientId">
                            <option value="">— Select client —</option>
                            @foreach ($clients as $c)
                                <option
                                    value="{{ $c->id }}"
                                    data-client-number="{{ $c->client_number }}"
                                    data-client-name="{{ $c->full_name }}"
                                    data-client-branch="{{ $c->branch ?? '' }}"
                                    data-client-officer="{{ $c->assignedEmployee?->full_name ?? '' }}"
                                    @selected(old('loan_client_id') == $c->id)
                                >{{ $c->client_number }} — {{ $c->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('loan_client_id')" />
                    </div>
                    <div>
                        <x-input-label for="to_branch" value="To branch" />
                        <div class="mt-1 flex items-center gap-2">
                            <select id="to_branch" name="to_branch" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="draft.toBranch">
                                <option value="">— Keep current —</option>
                                @foreach (($branchOptions ?? []) as $branchName)
                                    <option value="{{ $branchName }}" @selected(old('to_branch') === $branchName)>{{ $branchName }}</option>
                                @endforeach
                            </select>
                            <button
                                type="button"
                                id="open-branch-modal"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-lg font-bold text-slate-700 hover:bg-slate-50"
                                title="Create branch"
                            >+</button>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('to_branch')" />
                    </div>
                    <div>
                        <x-input-label for="to_employee_id" value="To officer" />
                        <select id="to_employee_id" name="to_employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="draft.toEmployeeId">
                            <option value="">— Keep current —</option>
                            @foreach ($employees as $employee)
                                <option
                                    value="{{ $employee->id }}"
                                    data-branch="{{ strtolower(trim((string) ($employee->branch ?? ''))) }}"
                                    @selected(old('to_employee_id') == $employee->id)
                                >{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('to_employee_id')" />
                    </div>
                    <div>
                        <x-input-label for="reason" value="Reason (optional)" />
                        <textarea id="reason" name="reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="draft.reason">{{ old('mode') === 'single' ? old('reason') : '' }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                    </div>
                    <x-primary-button type="submit">{{ __('Apply transfer') }}</x-primary-button>
                </form>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-700">Transfer draft</h2>
                    <div class="mt-3 space-y-2 text-xs text-slate-700">
                        <p><span class="font-semibold text-slate-600">Client:</span> <span x-text="selectedClientLabel || '—'"></span></p>
                        <p><span class="font-semibold text-slate-600">Current branch:</span> <span x-text="selectedClientBranch || '—'"></span></p>
                        <p><span class="font-semibold text-slate-600">Current officer:</span> <span x-text="selectedClientOfficer || '—'"></span></p>
                        <p><span class="font-semibold text-slate-600">To branch:</span> <span x-text="selectedToBranchLabel || 'Keep current'"></span></p>
                        <p><span class="font-semibold text-slate-600">To officer:</span> <span x-text="selectedToOfficerLabel || 'Keep current'"></span></p>
                        <p><span class="font-semibold text-slate-600">Reason:</span> <span x-text="draft.reason?.trim() ? draft.reason : '—'"></span></p>
                    </div>
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
        </div>
    </x-loan.page>
</x-loan-layout>

<div id="branch-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/55 p-4">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h3 class="text-lg font-semibold text-slate-800">Create branch</h3>
            <button type="button" id="close-branch-modal" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700">✕</button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label for="new_branch_name" class="block text-xs font-semibold text-slate-600 mb-1">Branch name</label>
                <input id="new_branch_name" type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Nakuru Town" />
            </div>
            <p id="branch-modal-error" class="hidden text-xs text-red-600"></p>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancel-branch-modal" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" id="save-branch-btn" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Save branch</button>
            </div>
        </div>
    </div>
</div>

<script>
    function transferDraftPreview() {
        return {
            draft: {
                loanClientId: '',
                toBranch: '',
                toEmployeeId: '',
                reason: '',
            },
            clientMap: {},
            init() {
                const clientSelect = document.getElementById('loan_client_id');
                if (clientSelect) {
                    this.clientMap = Array.from(clientSelect.options).reduce((carry, option) => {
                        const value = String(option.value || '');
                        if (value === '') return carry;
                        carry[value] = {
                            label: (option.textContent || '').trim(),
                            number: String(option.dataset.clientNumber || '').trim(),
                            name: String(option.dataset.clientName || '').trim(),
                            branch: String(option.dataset.clientBranch || '').trim(),
                            officer: String(option.dataset.clientOfficer || '').trim(),
                        };
                        return carry;
                    }, {});
                    this.draft.loanClientId = String(clientSelect.value || '');
                }

                const branchSelect = document.getElementById('to_branch');
                if (branchSelect) this.draft.toBranch = String(branchSelect.value || '');

                const officerSelect = document.getElementById('to_employee_id');
                if (officerSelect) this.draft.toEmployeeId = String(officerSelect.value || '');

                const reasonInput = document.getElementById('reason');
                if (reasonInput) this.draft.reason = String(reasonInput.value || '');
            },
            get selectedClient() {
                return this.clientMap[this.draft.loanClientId] || null;
            },
            get selectedClientLabel() {
                return this.selectedClient?.label || '';
            },
            get selectedClientBranch() {
                return this.selectedClient?.branch || '';
            },
            get selectedClientOfficer() {
                return this.selectedClient?.officer || '';
            },
            get selectedToBranchLabel() {
                if (!this.draft.toBranch) return '';
                const branchSelect = document.getElementById('to_branch');
                const option = branchSelect?.options?.[branchSelect.selectedIndex];
                return (option?.textContent || '').trim();
            },
            get selectedToOfficerLabel() {
                if (!this.draft.toEmployeeId) return '';
                const officerSelect = document.getElementById('to_employee_id');
                const option = officerSelect?.options?.[officerSelect.selectedIndex];
                return (option?.textContent || '').trim();
            },
        };
    }

    (() => {
        const branchSelect = document.getElementById('to_branch');
        const openBtn = document.getElementById('open-branch-modal');
        const modal = document.getElementById('branch-modal');
        const closeBtn = document.getElementById('close-branch-modal');
        const cancelBtn = document.getElementById('cancel-branch-modal');
        const saveBtn = document.getElementById('save-branch-btn');
        const input = document.getElementById('new_branch_name');
        const errorEl = document.getElementById('branch-modal-error');
        const officerSelect = document.getElementById('to_employee_id');
        const normalize = (value) => String(value || '').trim().toLowerCase();
        const officerOptionsCache = officerSelect
            ? Array.from(officerSelect.options).map((opt) => ({
                value: opt.value,
                label: opt.textContent || '',
                branch: normalize(opt.dataset.branch),
            }))
            : [];
        const renderOfficerOptions = () => {
            if (!officerSelect) return;
            const selectedBranch = normalize(branchSelect?.value);
            const previous = officerSelect.value;
            const matching = selectedBranch === ''
                ? officerOptionsCache
                : officerOptionsCache.filter((opt) => opt.value === '' || opt.branch === selectedBranch);

            officerSelect.innerHTML = '';
            matching.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.label;
                option.dataset.branch = item.branch;
                officerSelect.appendChild(option);
            });

            if (matching.some((item) => item.value === previous)) {
                officerSelect.value = previous;
            } else {
                officerSelect.value = '';
            }
        };
        const openModal = () => {
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
            }
            if (input) {
                input.value = '';
                input.focus();
            }
        };
        const closeModal = () => {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        };

        openBtn?.addEventListener('click', openModal);
        closeBtn?.addEventListener('click', closeModal);
        cancelBtn?.addEventListener('click', closeModal);
        branchSelect?.addEventListener('change', renderOfficerOptions);
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });

        saveBtn?.addEventListener('click', async () => {
            const name = (input?.value || '').trim();
            if (!name) {
                if (errorEl) {
                    errorEl.textContent = 'Branch name is required.';
                    errorEl.classList.remove('hidden');
                }
                return;
            }

            try {
                saveBtn.disabled = true;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const payload = new FormData();
                payload.append('name', name);

                const response = await fetch(@json(route('loan.clients.branches.store')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload,
                });
                const data = await response.json();
                if (!response.ok || !data?.ok) {
                    throw new Error(data?.message || 'Failed to save branch.');
                }

                if (branchSelect) {
                    const value = String(data.branch?.name || name);
                    let option = Array.from(branchSelect.options).find((opt) => opt.value === value);
                    if (!option) {
                        option = document.createElement('option');
                        option.value = value;
                        option.textContent = value;
                        branchSelect.appendChild(option);
                    }
                    branchSelect.value = value;
                    renderOfficerOptions();
                }

                closeModal();
            } catch (error) {
                if (errorEl) {
                    errorEl.textContent = error instanceof Error ? error.message : 'Failed to save branch.';
                    errorEl.classList.remove('hidden');
                }
            } finally {
                saveBtn.disabled = false;
            }
        });
        renderOfficerOptions();
    })();
</script>
