@php
    $oldLines = old('lines', [
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
    ]);
@endphp
<x-property.workspace
    title="Journal batches"
    subtitle="Batch control center for posting flow, reversals, and traceability."
    back-route="property.accounting.index"
    :stats="[
        ['label' => 'Total Batches', 'value' => (string) ($summary['total_batches'] ?? 0), 'hint' => 'Filtered result'],
        ['label' => 'Total Debit', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($summary['total_debit'] ?? 0)), 'hint' => 'All batch lines'],
        ['label' => 'Total Credit', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($summary['total_credit'] ?? 0)), 'hint' => 'All batch lines'],
        ['label' => 'Posted Batches', 'value' => (string) ($summary['posted_batches'] ?? 0), 'hint' => 'Ready ledger impact'],
        ['label' => 'Reversed Batches', 'value' => (string) ($summary['reversed_batches'] ?? 0), 'hint' => 'Voided with opposite entry'],
    ]"
>
    <x-slot name="actions">
        <a href="#new-journal-batch" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            New Journal Batch
        </a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.gl.journal_batches') }}" class="w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <label class="block text-xs font-medium text-slate-600">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Source Type</label>
                    <select name="source_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach(($sourceTypes ?? collect()) as $sourceType)
                            <option value="{{ $sourceType }}" @selected(($filters['sourceType'] ?? '') === $sourceType)>{{ \Illuminate\Support\Str::headline((string) $sourceType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach (['draft', 'posted', 'reversed'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Property</label>
                    <select name="property_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="0">All</option>
                        @foreach(($properties ?? collect()) as $property)
                            <option value="{{ $property->id }}" @selected((int) ($filters['propertyId'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Created By</label>
                    <select name="created_by" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="0">All</option>
                        @foreach(($creators ?? collect()) as $creator)
                            <option value="{{ $creator->id }}" @selected((int) ($filters['createdBy'] ?? 0) === (int) $creator->id)>{{ $creator->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.accounting.gl.journal_batches') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </x-slot>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Batch ID</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Total Debit</th>
                        <th class="px-4 py-3">Total Credit</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        @php
                            $totals = $lineTotals->get($batch->id);
                            $lines = $batchLines->get($batch->id, collect());
                            $source = $sourceLinks[(int) $batch->id] ?? ['label' => \Illuminate\Support\Str::headline((string) $batch->source_type), 'url' => null];
                            $isReversible = (string) $batch->status === \App\Models\AccountingJournalBatch::STATUS_POSTED && is_null($batch->reversed_at);
                            $statusClass = match ((string) $batch->status) {
                                \App\Models\AccountingJournalBatch::STATUS_POSTED => 'bg-emerald-100 text-emerald-700',
                                \App\Models\AccountingJournalBatch::STATUS_REVERSED => 'bg-rose-100 text-rose-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <tr class="border-t border-slate-100 cursor-pointer hover:bg-slate-50" data-expand-trigger data-expand-target="batch-lines-{{ $batch->id }}">
                            <td class="px-4 py-3 font-semibold text-slate-900">#{{ $batch->id }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ optional($batch->date)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                @if(!empty($source['url']))
                                    <a class="text-blue-600 hover:underline" href="{{ $source['url'] }}">{{ $source['label'] }}</a>
                                @else
                                    {{ $source['label'] }}
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals->debit_total ?? 0)) }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals->credit_total ?? 0)) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst((string) $batch->status) }}</span>
                            </td>
                            <td class="px-4 py-3" data-row-ignore-click>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('property.accounting.entries.show', ['batch' => $batch->id]) }}" class="text-indigo-600 hover:text-indigo-700">View</a>
                                    @if($isReversible)
                                        <form method="post" action="{{ route('property.accounting.entries.reverse', ['entry' => $batch->id]) }}" onsubmit="return confirm('Reverse this posted batch?');">
                                            @csrf
                                            <button type="submit" class="text-rose-600 hover:text-rose-700">Reverse</button>
                                        </form>
                                    @else
                                        <span class="text-slate-400">Reverse</span>
                                    @endif
                                    <a href="{{ route('property.accounting.gl.journal_batches.export', ['batch' => $batch->id]) }}" class="text-slate-700 hover:text-slate-900">Export</a>
                                </div>
                            </td>
                        </tr>
                        <tr id="batch-lines-{{ $batch->id }}" class="hidden bg-slate-50/70">
                            <td colspan="7" class="px-4 py-3">
                                <div class="rounded-xl border border-slate-200 bg-white">
                                    <div class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Journal lines ({{ $lines->count() }})
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                <tr>
                                                    <th class="px-4 py-2">Account</th>
                                                    <th class="px-4 py-2">Debit</th>
                                                    <th class="px-4 py-2">Credit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($lines as $line)
                                                    <tr class="border-t border-slate-100">
                                                        <td class="px-4 py-2 text-slate-700">
                                                            {{ $line->structuredAccount?->code }} {{ $line->structuredAccount?->name ? '- '.$line->structuredAccount->name : ($line->memo ?: '—') }}
                                                        </td>
                                                        <td class="px-4 py-2 font-medium text-slate-800">{{ \App\Services\Property\PropertyMoney::kes((float) $line->debit) }}</td>
                                                        <td class="px-4 py-2 font-medium text-slate-800">{{ \App\Services\Property\PropertyMoney::kes((float) $line->credit) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="px-4 py-4 text-center text-slate-500">No lines found for this batch.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <p class="text-sm font-semibold text-slate-800">No journal batches yet.</p>
                                <p class="mt-2 text-sm text-slate-600">Journal batches group balanced debit and credit postings from invoices, payments, maintenance, payroll, and manual entries.</p>
                                <a href="#new-journal-batch" class="mt-4 inline-flex rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create First Batch</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="new-journal-batch" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <form method="post" action="{{ route('property.accounting.entries.store') }}" id="journal-builder-form">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900">Create manual journal batch</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Date</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Reference</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Auto if blank" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Property (optional)</label>
                    <select name="property_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">General</option>
                        @foreach(($properties ?? collect()) as $property)
                            <option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Description</label>
                    <input type="text" name="description" value="{{ old('description') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Journal narrative" />
                </div>
            </div>
            @error('lines')<p class="mt-2 text-xs text-rose-700">{{ $message }}</p>@enderror
            <div class="mt-3 overflow-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Account</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">Debit</th>
                            <th class="px-3 py-2">Credit</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody id="journal-lines-body">
                        @foreach($oldLines as $i => $line)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    <select name="lines[{{ $i }}][account_id]" required class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                                        <option value="">Select account</option>
                                        @foreach(($accounts ?? collect()) as $acc)
                                            <option value="{{ $acc->id }}" @selected((string)($line['account_id'] ?? '') === (string)$acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2"><input type="text" name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" /></td>
                                <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? '' }}" class="line-debit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" /></td>
                                <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? '' }}" class="line-credit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" /></td>
                                <td class="px-3 py-2"><button type="button" class="remove-line rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" id="add-line" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Add line</button>
                <p class="text-xs text-slate-500">At least two lines are required, and debit must equal credit.</p>
            </div>
            <div id="journal-totals" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="grid gap-2 sm:grid-cols-3 text-sm">
                    <p><span class="font-semibold">Total Debit:</span> <span id="total-debit">KES 0.00</span></p>
                    <p><span class="font-semibold">Total Credit:</span> <span id="total-credit">KES 0.00</span></p>
                    <p><span class="font-semibold">Difference:</span> <span id="total-diff">KES 0.00</span></p>
                </div>
                <p id="balance-hint" class="mt-2 text-xs font-semibold text-rose-700">Unbalanced entry.</p>
            </div>
            <button type="submit" id="submit-journal" class="mt-3 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-400">Post journal</button>
        </form>
    </div>

    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $batches])
    </x-slot>

    <script>
        (function () {
            const interactiveSelector = 'a, button, input, select, textarea, form';
            document.querySelectorAll('[data-expand-trigger]').forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target && event.target.closest(interactiveSelector)) return;
                    const targetId = row.getAttribute('data-expand-target');
                    if (!targetId) return;
                    const detailsRow = document.getElementById(targetId);
                    if (!detailsRow) return;
                    detailsRow.classList.toggle('hidden');
                });
            });

            const currency = new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' });
            const body = document.getElementById('journal-lines-body');
            const addBtn = document.getElementById('add-line');
            const submitBtn = document.getElementById('submit-journal');
            const totalDebitEl = document.getElementById('total-debit');
            const totalCreditEl = document.getElementById('total-credit');
            const totalDiffEl = document.getElementById('total-diff');
            const balanceHint = document.getElementById('balance-hint');
            const accountOptionsHtml = @json(($accounts ?? collect())->map(fn ($acc) => '<option value="'.$acc->id.'">'.$acc->code.' - '.e($acc->name).'</option>')->implode(''));

            const computeTotals = () => {
                if (!body) return;
                let debit = 0;
                let credit = 0;
                body.querySelectorAll('tr').forEach((row) => {
                    const d = parseFloat(row.querySelector('.line-debit')?.value || '0');
                    const c = parseFloat(row.querySelector('.line-credit')?.value || '0');
                    debit += Number.isFinite(d) ? d : 0;
                    credit += Number.isFinite(c) ? c : 0;
                });
                const diff = debit - credit;
                totalDebitEl.textContent = currency.format(debit);
                totalCreditEl.textContent = currency.format(credit);
                totalDiffEl.textContent = currency.format(diff);
                const isBalanced = Math.abs(diff) < 0.0001 && body.querySelectorAll('tr').length >= 2;
                submitBtn.disabled = !isBalanced;
                balanceHint.textContent = isBalanced ? 'Balanced entry. Ready to post.' : 'Unbalanced entry.';
                balanceHint.classList.toggle('text-emerald-700', isBalanced);
                balanceHint.classList.toggle('text-rose-700', !isBalanced);
            };

            const reindexRows = () => {
                if (!body) return;
                body.querySelectorAll('tr').forEach((row, idx) => {
                    row.querySelectorAll('select,input').forEach((input) => {
                        const name = input.getAttribute('name') || '';
                        input.setAttribute('name', name.replace(/lines\[\d+\]/, `lines[${idx}]`));
                    });
                });
            };

            addBtn?.addEventListener('click', () => {
                if (!body) return;
                const idx = body.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.className = 'border-t border-slate-100';
                tr.innerHTML = `
                    <td class="px-3 py-2"><select name="lines[${idx}][account_id]" required class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"><option value="">Select account</option>${accountOptionsHtml}</select></td>
                    <td class="px-3 py-2"><input type="text" name="lines[${idx}][description]" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" /></td>
                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${idx}][debit]" class="line-debit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" /></td>
                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${idx}][credit]" class="line-credit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" /></td>
                    <td class="px-3 py-2"><button type="button" class="remove-line rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Remove</button></td>
                `;
                body.appendChild(tr);
                computeTotals();
            });

            body?.addEventListener('input', computeTotals);
            body?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-line')) return;
                if (body.querySelectorAll('tr').length <= 2) return;
                target.closest('tr')?.remove();
                reindexRows();
                computeTotals();
            });
            computeTotals();
        })();
    </script>
</x-property.workspace>

