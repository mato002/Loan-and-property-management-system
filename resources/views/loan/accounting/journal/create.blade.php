@php
    $seedLines = old('lines');
    if (!is_array($seedLines) || count($seedLines) === 0) {
        if (isset($draftEntry) && $draftEntry?->lines?->count()) {
            $seedLines = $draftEntry->lines->map(fn ($line) => [
                'accounting_chart_account_id' => (int) $line->accounting_chart_account_id,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'memo' => (string) ($line->memo ?? ''),
            ])->values()->all();
        } else {
            $seedLines = [
                ['accounting_chart_account_id' => null, 'debit' => 0, 'credit' => 0, 'memo' => ''],
                ['accounting_chart_account_id' => null, 'debit' => 0, 'credit' => 0, 'memo' => ''],
            ];
        }
    }

    $accountOptions = collect($accounts ?? [])->map(fn ($a) => [
        'id' => (int) $a->id,
        'code' => (string) $a->code,
        'name' => (string) $a->name,
        'restricted' => (bool) $a->is_controlled_account && (bool) $a->control_requires_approval,
        'threshold' => (float) ($a->control_threshold_amount ?? 0),
        'floor' => (float) ($a->min_balance_floor ?? 0),
        'starting_balance' => (float) ($a->current_balance ?? 0),
    ])->values();

    $templatePayload = collect($templates ?? [])->map(fn ($template) => [
        'id' => (int) $template->id,
        'name' => (string) $template->name,
        'description' => (string) ($template->description ?? ''),
        'scope' => (string) $template->scope,
        'default_action' => (string) $template->default_action,
        'reference_prefix' => (string) ($template->reference_prefix ?? ''),
        'template_lines' => collect($template->template_lines ?? [])->map(fn ($line) => [
            'accounting_chart_account_id' => (int) ($line['accounting_chart_account_id'] ?? 0),
            'debit' => (float) ($line['debit'] ?? 0),
            'credit' => (float) ($line['credit'] ?? 0),
            'memo' => (string) ($line['memo'] ?? ''),
        ])->values()->all(),
    ])->values();
@endphp

<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Create, draft, and submit manual journal entries with real approval governance.">
        @include('loan.accounting.partials.flash')
        <div class="space-y-4" x-data="journalCommandCenter(@js($accountOptions), @js($seedLines), @js($templatePayload))">
            <section class="grid gap-3 md:grid-cols-3">
                <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-orange-700">Blocked / Rejected</p>
                    <p class="mt-2 text-3xl font-semibold text-orange-700">{{ (int) ($blockedCount ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Needs correction before posting.</p>
                </article>
                <article class="rounded-xl border border-purple-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Approval Queue</p>
                    <p class="mt-2 text-3xl font-semibold text-purple-700">{{ (int) ($pendingApprovals ?? 0) }}</p>
                    <div class="mt-2 border-t border-purple-100 pt-2 text-xs text-purple-800">
                        <div class="flex items-center justify-between">
                            <span>Entries waiting approval</span>
                            <span class="font-semibold">{{ (int) ($pendingApprovals ?? 0) }}</span>
                        </div>
                    </div>
                </article>
                <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Drafts</p>
                    <p class="mt-2 text-3xl font-semibold text-blue-700">{{ (int) ($draftCount ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Saved entries pending completion.</p>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-12">
                <aside class="xl:col-span-3 space-y-4">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Saved Templates</h3>
                        <div class="mt-3 space-y-2 max-h-72 overflow-y-auto">
                            @forelse ($templates as $template)
                                <div class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <div class="flex items-start justify-between gap-2">
                                        <button type="button" class="text-left flex-1 hover:text-blue-700" @click="applyTemplate({{ (int) $template->id }})">
                                            <div class="font-medium text-slate-800">{{ $template->name }}</div>
                                            <div class="text-xs text-slate-500">{{ ucfirst($template->scope) }} template</div>
                                        </button>
                                        <div class="flex items-center gap-1">
                                            <button type="button" @click="editTemplate({{ (int) $template->id }})" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-blue-700" title="Edit template" aria-label="Edit template">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </button>
                                            <form method="post" action="{{ route('loan.accounting.journal.templates.destroy', $template) }}">
                                                @csrf
                                                <button type="submit" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-red-700" title="Delete template" aria-label="Delete template" data-swal-confirm="Delete this template?">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">No templates yet.</p>
                            @endforelse
                        </div>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900" x-text="editingTemplateId ? 'Edit Template' : 'Create Template'"></h3>
                        <form method="post" :action="editingTemplateId ? templateUpdateUrl(editingTemplateId) : '{{ route('loan.accounting.journal.templates.store') }}'" class="mt-3 space-y-2">
                            @csrf
                            <input type="text" name="name" x-model="templateForm.name" required maxlength="120" placeholder="Template name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input type="text" name="description" x-model="templateForm.description" maxlength="500" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input type="hidden" name="scope" value="personal">
                            <input type="hidden" name="default_action" value="post">
                            <input type="text" name="reference_prefix" x-model="templateForm.reference_prefix" maxlength="30" placeholder="Reference prefix (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-[420px] w-full text-xs">
                                    <thead class="bg-slate-50 text-slate-600 uppercase">
                                        <tr>
                                            <th class="px-2 py-2 text-left">Account</th>
                                            <th class="px-2 py-2 text-right">Debit</th>
                                            <th class="px-2 py-2 text-right">Credit</th>
                                            <th class="px-2 py-2 text-left">Memo</th>
                                            <th class="px-2 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(line, i) in templateLines" :key="line.uid">
                                            <tr>
                                                <td class="px-2 py-2">
                                                    <select :name="`template_lines[${i}][accounting_chart_account_id]`" x-model.number="line.accounting_chart_account_id" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                                        <option value="">--</option>
                                                        <template x-for="a in accountOptions" :key="`t-${a.id}`">
                                                            <option :value="a.id" x-text="`${a.code}`"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="number" min="0" step="0.01" :name="`template_lines[${i}][debit]`" x-model.number="line.debit" class="w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs">
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="number" min="0" step="0.01" :name="`template_lines[${i}][credit]`" x-model.number="line.credit" class="w-20 rounded border border-slate-300 px-2 py-1 text-right text-xs">
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="text" :name="`template_lines[${i}][memo]`" x-model="line.memo" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                                </td>
                                                <td class="px-2 py-2">
                                                    <button type="button" @click="removeTemplateLine(i)" class="text-slate-500 hover:text-red-600">x</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" @click="addTemplateLine()" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Add template line</button>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" x-show="editingTemplateId" @click="resetTemplateForm()" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Cancel edit</button>
                                <button type="submit" class="rounded-lg bg-teal-800 px-3 py-2 text-sm font-semibold text-white" x-text="editingTemplateId ? 'Update Template' : 'Save Template'"></button>
                            </div>
                        </form>
                    </article>
                </aside>

                <main class="xl:col-span-6">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Smart Journal Entry Form</h2>
                        <form method="post" action="{{ route('loan.accounting.journal.store') }}" class="mt-4 space-y-4">
                            @csrf
                            <input type="hidden" name="journal_entry_id" value="{{ (int) old('journal_entry_id', $draftEntry->id ?? 0) }}">
                            <div class="grid gap-3 md:grid-cols-3">
                                <input name="entry_date" type="date" value="{{ old('entry_date', optional($draftEntry->entry_date ?? now())->format('Y-m-d')) }}" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input name="reference" value="{{ old('reference', $draftEntry->reference ?? '') }}" placeholder="Reference" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input name="description" value="{{ old('description', $draftEntry->description ?? '') }}" placeholder="Description" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            @error('lines')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="min-w-[760px] w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Account</th>
                                            <th class="px-3 py-2 text-right">Debit</th>
                                            <th class="px-3 py-2 text-right">Credit</th>
                                            <th class="px-3 py-2 text-left">Memo</th>
                                            <th class="px-3 py-2 text-right">Projected</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(line, i) in lines" :key="line.uid">
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <select :name="`lines[${i}][accounting_chart_account_id]`" x-model.number="line.accounting_chart_account_id" @change="syncLine(i)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                                        <option value="">--</option>
                                                        <template x-for="a in accountOptions" :key="a.id">
                                                            <option :value="a.id" x-text="`${a.code} · ${a.name}`"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="`lines[${i}][debit]`" x-model.number="line.debit" @input="syncLine(i)" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right"></td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="`lines[${i}][credit]`" x-model.number="line.credit" @input="syncLine(i)" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right"></td>
                                                <td class="px-3 py-2"><input type="text" :name="`lines[${i}][memo]`" x-model="line.memo" class="w-full rounded-lg border border-slate-300 px-2 py-1.5"></td>
                                                <td class="px-3 py-2 text-right"><span :class="line.belowFloor ? 'text-orange-700 font-semibold' : 'text-emerald-700 font-semibold'" x-text="formatKsh(line.projectedBalance)"></span></td>
                                                <td class="px-3 py-2"><button type="button" @click="removeLine(i)" class="text-slate-500 hover:text-red-600">x</button></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="button" @click="addLine()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">Add Line</button>
                                <div class="text-xs font-semibold text-slate-600">Debit: <span x-text="formatKsh(totalDebit)"></span> | Credit: <span x-text="formatKsh(totalCredit)"></span></div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="submit" name="action" value="post" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Post Transaction</button>
                                <button type="submit" name="action" value="submit_for_approval" class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Submit for Approval</button>
                                <button type="submit" name="action" value="save_draft" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Save as Draft</button>
                            </div>
                        </form>
                    </article>
                </main>

                <aside class="xl:col-span-3">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Last 10 Manual Journal Activities</h3>
                        <div class="mt-3 max-h-[560px] space-y-2 overflow-y-auto">
                            @forelse ($recentActivities as $entry)
                                <div class="rounded-lg border border-slate-200 px-3 py-2">
                                    <div class="text-xs text-slate-500">{{ optional($entry->entry_date)->format('Y-m-d') }} · {{ $entry->createdByUser?->name ?? 'System' }}</div>
                                    <a href="{{ route('loan.accounting.journal.show', $entry) }}" class="text-sm font-medium text-slate-800 hover:text-blue-700">{{ $entry->reference ?: ('JE-'.$entry->id) }}</a>
                                    <div class="mt-1 text-xs text-slate-600">{{ ucfirst((string) $entry->status) }}</div>
                                    <div class="mt-2 flex items-center gap-2">
                                        @if (in_array((string) $entry->status, [\App\Models\AccountingJournalEntry::STATUS_DRAFT, \App\Models\AccountingJournalEntry::STATUS_REJECTED], true))
                                            <a href="{{ route('loan.accounting.journal.edit', $entry) }}" class="rounded border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700">Edit</a>
                                        @endif
                                        @if ((string) $entry->status === \App\Models\AccountingJournalEntry::STATUS_POSTED)
                                            <form method="post" action="{{ route('loan.accounting.journal.reverse', $entry) }}">
                                                @csrf
                                                <button type="submit" class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700" data-swal-confirm="Reverse this posted journal entry?">Reverse</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">No journal activity yet.</p>
                            @endforelse
                        </div>
                    </article>
                </aside>
            </section>
        </div>
    </x-loan.page>

    <script>
        function journalCommandCenter(accountOptions, seedLines, templates) {
            const mkLine = (row = {}, idx = 0) => ({
                uid: `${Date.now()}-${idx}-${Math.random().toString(16).slice(2)}`,
                accounting_chart_account_id: row.accounting_chart_account_id ? Number(row.accounting_chart_account_id) : null,
                debit: Number(row.debit || 0),
                credit: Number(row.credit || 0),
                memo: row.memo || '',
                projectedBalance: 0,
                belowFloor: false,
            });
            return {
                accountOptions: Array.isArray(accountOptions) ? accountOptions : [],
                templates: Array.isArray(templates) ? templates : [],
                lines: (Array.isArray(seedLines) ? seedLines : []).map((row, i) => mkLine(row, i)),
                editingTemplateId: null,
                templateForm: { name: '', description: '', reference_prefix: '' },
                templateLines: [mkLine({}, 1001), mkLine({}, 1002)],
                init() { this.lines.forEach((_, i) => this.syncLine(i)); },
                get totalDebit() { return this.lines.reduce((sum, line) => sum + (Number(line.debit) || 0), 0); },
                get totalCredit() { return this.lines.reduce((sum, line) => sum + (Number(line.credit) || 0), 0); },
                syncLine(i) {
                    const line = this.lines[i];
                    if (!line) return;
                    const account = this.accountOptions.find((a) => Number(a.id) === Number(line.accounting_chart_account_id));
                    if (!account) {
                        line.projectedBalance = 0;
                        line.belowFloor = false;
                        return;
                    }
                    line.projectedBalance = Number(account.starting_balance || 0) + Number(line.debit || 0) - Number(line.credit || 0);
                    line.belowFloor = Number(account.floor || 0) > 0 && line.projectedBalance < Number(account.floor || 0);
                },
                addLine() { this.lines.push(mkLine({}, this.lines.length)); },
                removeLine(i) {
                    if (this.lines.length <= 1) return;
                    this.lines.splice(i, 1);
                },
                applyTemplate(templateId) {
                    const template = this.templates.find((item) => Number(item.id) === Number(templateId));
                    if (!template) return;
                    const rows = Array.isArray(template.template_lines) ? template.template_lines : [];
                    this.lines = rows.length > 0
                        ? rows.map((row, i) => mkLine({
                            accounting_chart_account_id: row.accounting_chart_account_id,
                            debit: Number(row.debit || 0),
                            credit: Number(row.credit || 0),
                            memo: row.memo || '',
                        }, i))
                        : [mkLine({}, 0)];
                    this.lines.forEach((_, i) => this.syncLine(i));
                },
                addTemplateLine() {
                    this.templateLines.push(mkLine({}, 2000 + this.templateLines.length));
                },
                removeTemplateLine(i) {
                    if (this.templateLines.length <= 1) return;
                    this.templateLines.splice(i, 1);
                },
                editTemplate(templateId) {
                    const template = this.templates.find((item) => Number(item.id) === Number(templateId));
                    if (!template) return;
                    this.editingTemplateId = Number(template.id);
                    this.templateForm = {
                        name: String(template.name || ''),
                        description: String(template.description || ''),
                        reference_prefix: String(template.reference_prefix || ''),
                    };
                    const rows = Array.isArray(template.template_lines) ? template.template_lines : [];
                    this.templateLines = rows.length > 0
                        ? rows.map((row, i) => mkLine(row, 3000 + i))
                        : [mkLine({}, 3000)];
                },
                resetTemplateForm() {
                    this.editingTemplateId = null;
                    this.templateForm = { name: '', description: '', reference_prefix: '' };
                    this.templateLines = [mkLine({}, 1001), mkLine({}, 1002)];
                },
                templateUpdateUrl(templateId) {
                    return `{{ url('/loan/accounting/journal-entries/templates') }}/${templateId}`;
                },
                formatKsh(value) {
                    return `KSh ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                },
            };
        }
    </script>
</x-loan-layout>
