@php
    $accountPayload = collect($accounts ?? [])->map(function ($a) {
        $name = strtolower((string) $a->name);
        $isCashLike = str_contains($name, 'cash') || str_contains($name, 'bank') || str_contains($name, 'm-pesa');
        return [
            'id' => (int) $a->id,
            'code' => (string) $a->code,
            'name' => (string) $a->name,
            'restricted' => str_contains($name, 'director') || str_contains($name, 'equity'),
            'floor' => $isCashLike ? 5000 : 0,
            'starting_balance' => $isCashLike ? 52000 : 120000,
        ];
    })->values();
@endphp

<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Manage manual journal entries, adjustments, and non-routine cash movements.">
        <div class="min-h-full space-y-4 bg-slate-50 p-3 sm:p-5 lg:p-6" x-data="journalCommandCenter(@js($accountPayload))" @keydown.escape.window="closeModal()">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Journal Entry Command Center</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage manual journal entries, adjustments, and non-routine cash movements.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 lg:items-end">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium text-slate-600">Thursday, April 23, 2026</span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="openModal()" class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v5H4zM4 14h10v5H4zM17 14h3v5h-3"></path></svg>
                                Quick Access &amp; Templates
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                            <button type="button" @click="openModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Open quick access modal">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-12">
                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-900">Templates &amp; Quick Access</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personal Favorites</p>
                                <div class="mt-2 space-y-2">
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 text-left text-sm text-blue-700">M-Pesa to Bank Transfer <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 text-left text-sm text-blue-700">Petty Cash Replenishment <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-purple-100 bg-purple-50/80 px-3 py-2 text-left text-sm text-purple-700">Director Equity Contribution <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-purple-100 bg-purple-50/80 px-3 py-2 text-left text-sm text-purple-700">Salary Advance Issuance <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-orange-100 bg-orange-50/80 px-3 py-2 text-left text-sm text-orange-700">Reversal of Previous Journal <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">System Defined</p>
                                <div class="mt-2 space-y-2">
                                    <button type="button" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700">Inter-Account Transfer</button>
                                    <button type="button" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700">Bank Charges Allocation</button>
                                    <button type="button" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700">Interest Income Recognition</button>
                                </div>
                            </div>
                            <button type="button" class="w-full rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">View Full Posted History</button>
                        </div>
                    </article>
                </aside>

                <main class="space-y-4 xl:col-span-6">
                    <section class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-orange-700">Blocked Drafts (The Liquidity Queue)</p>
                                <svg class="h-4 w-4 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 2"></path><circle cx="12" cy="12" r="9"></circle></svg>
                            </div>
                            <p class="mt-2 text-3xl font-semibold text-orange-700">7 Entries</p>
                            <p class="mt-1 text-xs text-slate-500">Violations of Min. Balance Floor rules.</p>
                        </article>
                        <article class="rounded-xl border border-purple-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Approval Queue</p>
                                <svg class="h-4 w-4 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.9 8 10 4.6-1.1 8-5 8-10V7l-8-4Z"></path></svg>
                            </div>
                            <p class="mt-2 text-3xl font-semibold text-purple-700">3 Entries</p>
                            <p class="mt-1 text-xs text-slate-500">Awaiting Director authorization</p>
                        </article>
                        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Drafts &amp; Unposted</p>
                                <svg class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </div>
                            <p class="mt-2 text-3xl font-semibold text-blue-700">5 Entries</p>
                            <p class="mt-1 text-xs text-slate-500">Not yet posted to the general ledger</p>
                        </article>
                    </section>

                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Smart Journal Entry Form</h2>
                                <p class="text-sm text-slate-600">New Journal Entry</p>
                            </div>
                            <span class="inline-flex items-center gap-2 self-start rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.9 8 10 4.6-1.1 8-5 8-10V7l-8-4Z"></path></svg>
                                DNA Check: Director Approval Required
                            </span>
                        </div>

                        <form method="post" action="{{ route('loan.accounting.journal.store') }}" class="space-y-4">
                            @csrf
                            <div class="grid gap-3 md:grid-cols-3">
                                <label class="block text-sm">
                                    <span class="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Date <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></span>
                                    <input name="entry_date" type="date" value="{{ old('entry_date', now()->toDateString()) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                                    @error('entry_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Reference <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></span>
                                    <input name="reference" value="{{ old('reference') }}" placeholder="Reference" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Description <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></span>
                                    <input name="description" value="{{ old('description') }}" placeholder="Enter description of the transaction" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                                </label>
                            </div>

                            <p class="text-xs text-slate-500">DNA Check is enabled for selected accounts <span class="font-medium text-slate-700">if applicable based on account rules.</span></p>
                            @error('lines')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="min-w-[780px] w-full text-sm">
                                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        <tr>
                                            <th class="px-3 py-2">Account (DNA-checked)</th>
                                            <th class="px-3 py-2 text-right">Debit (KSh)</th>
                                            <th class="px-3 py-2 text-right">Credit (KSh)</th>
                                            <th class="px-3 py-2">Notes</th>
                                            <th class="px-3 py-2 text-right">Projected Balance (KSh)</th>
                                            <th class="px-3 py-2 text-center">Audit</th>
                                        </tr>
                                    </thead>
                                    @php
                                        $oldLines = old('lines', []);
                                        $rows = is_array($oldLines) && count($oldLines) > 0 ? array_values($oldLines) : [
                                            ['accounting_chart_account_id' => null, 'debit' => null, 'credit' => null, 'memo' => ''],
                                            ['accounting_chart_account_id' => null, 'debit' => null, 'credit' => null, 'memo' => ''],
                                            ['accounting_chart_account_id' => null, 'debit' => null, 'credit' => null, 'memo' => ''],
                                        ];
                                    @endphp
                                    <tbody id="journal-lines-body" class="divide-y divide-slate-100" data-next-index="{{ count($rows) }}">
                                        @foreach ($rows as $i => $line)
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <select name="lines[{{ $i }}][accounting_chart_account_id]" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" x-model.number="rows[{{ $i }}].accountId" @change="syncRow({{ $i }})">
                                                        <option value="">—</option>
                                                        @foreach ($accounts as $a)
                                                            <option value="{{ $a->id }}" @selected((string) data_get($line, 'accounting_chart_account_id') === (string) $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ data_get($line, 'debit') }}" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm" x-model.number="rows[{{ $i }}].debit" @input="syncRow({{ $i }})">
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ data_get($line, 'credit') }}" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm" x-model.number="rows[{{ $i }}].credit" @input="syncRow({{ $i }})">
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="text" name="lines[{{ $i }}][memo]" value="{{ data_get($line, 'memo') }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" x-model="rows[{{ $i }}].memo">
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <span class="font-semibold" :class="rows[{{ $i }}].belowFloor ? 'text-orange-700' : 'text-emerald-700'" x-text="formatKsh(rows[{{ $i }}].projectedBalance)"></span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <button type="button" class="text-slate-400 hover:text-slate-700" aria-label="Audit history">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <button type="button" id="add-line-btn" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>
                                    Add Journal Line
                                </button>
                                <div class="text-xs font-semibold text-slate-600">
                                    Total Debit (KSh): <span x-text="formatKsh(totalDebit)"></span>
                                    <span class="mx-2 text-slate-300">|</span>
                                    Total Credit (KSh): <span x-text="formatKsh(totalCredit)"></span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span x-show="attachmentRequired" class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 11V7a4 4 0 1 1 8 0v4"></path><rect x="5" y="11" width="14" height="10" rx="2"></rect></svg>
                                    Attachment Required
                                </span>
                                <div x-show="hasFloorViolation" class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700">
                                    Projected Balance: <span x-text="formatKsh(lowestProjectedBalance)"></span> (Below COA Floor: <span x-text="formatKsh(lowestFloor)"></span>)
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button type="submit" x-show="!hasFloorViolation && !requiresApproval" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Post Transaction</button>
                                <button type="button" x-show="!hasFloorViolation && requiresApproval" class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-800">Submit for Approval</button>
                                <button type="button" x-show="hasFloorViolation" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">Save as Blocked Draft</button>
                                <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save as Draft</button>
                            </div>

                            <p class="text-xs text-slate-500">When a high-value account is selected, or the amount pushes the projected balance below the COA floor, the amount field highlights in orange.</p>
                        </form>
                    </article>
                </main>

                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-900">Last 15 Journal Activities</h2>
                        <div class="mt-3 max-h-[760px] space-y-2 overflow-y-auto pr-1">
                            <template x-for="activity in activities" :key="activity.id">
                                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-semibold text-slate-500" x-text="activity.time"></p>
                                            <p class="text-sm font-medium text-slate-800" x-text="activity.title"></p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" x-show="activity.status === 'Posted'" class="rounded p-1 text-orange-600 hover:bg-orange-50" aria-label="Reverse">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 7H3v4"></path><path d="M3 11a9 9 0 1 0 3-6.7"></path></svg>
                                            </button>
                                            <button type="button" x-show="activity.status === 'Blocked Draft'" class="rounded p-1 text-blue-600 hover:bg-blue-50" aria-label="Retry">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12a9 9 0 1 1-3-6.7"></path><path d="M21 3v6h-6"></path></svg>
                                            </button>
                                            <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="History">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between">
                                        <span class="text-xs font-semibold" :class="activity.status === 'Posted' ? 'text-emerald-700' : (activity.status === 'Pending Approval' ? 'text-purple-700' : 'text-orange-700')" x-text="activity.status"></span>
                                        <span class="text-xs text-slate-500" x-text="activity.amount"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </article>
                </aside>
            </section>

            <div x-show="templateModalOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/45" @click="closeModal()"></div>
            <section x-show="templateModalOpen" class="fixed inset-x-3 top-[7%] z-50 mx-auto w-full max-w-5xl rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl sm:inset-x-6 sm:p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-xl font-semibold text-slate-900">Create New Quick Access Template</h3>
                        <p class="mt-1 text-sm text-slate-500">Configure reusable accounting templates with governance and approval controls.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">Config Popup</span>
                        <button type="button" @click="closeModal()" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="rounded-xl border border-slate-200 p-3">
                        <h4 class="text-sm font-semibold text-slate-900">1. General Information</h4>
                        <div class="mt-3 space-y-2">
                            <input type="text" placeholder="Template Name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <textarea rows="2" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                            <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>Personal</option><option>System</option></select>
                            <button type="button" class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">View / Manage All</button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-slate-900">2. Accounting DNA Mappings</h4>
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">DNA-checked</span>
                        </div>
                        <div class="mt-3 space-y-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">Debit Account (COA)</label>
                            <select x-model.number="templateDebitId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select account</option>
                                <template x-for="opt in accountOptions" :key="`d-${opt.id}`"><option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option></template>
                            </select>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">Credit Account (COA)</label>
                            <select x-model.number="templateCreditId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select account</option>
                                <template x-for="opt in accountOptions" :key="`c-${opt.id}`"><option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option></template>
                            </select>
                            <input type="text" placeholder="Reference Prefix" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <div class="space-y-1 pt-1 text-sm">
                                <label class="flex items-center gap-2"><input type="radio" name="amount_type" checked class="h-4 w-4 border-slate-300 text-blue-600"> Fixed Amount</label>
                                <label class="flex items-center gap-2"><input type="radio" name="amount_type" class="h-4 w-4 border-slate-300 text-blue-600"> Variable Amount</label>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-3">
                        <h4 class="text-sm font-semibold text-slate-900">3. Governance &amp; Rules Builder</h4>
                        <div class="mt-3 space-y-2 text-sm">
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Requires Dual Authorization</span><input type="checkbox" checked class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Requires Mandatory Cost Center Tag</span><input type="checkbox" class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Requires Mandatory Client / Loan ID</span><input type="checkbox" class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Enforce Transaction Limit</span><input type="checkbox" checked class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>Authorized Approver / Checker</option></select>
                            <input type="text" value="100,000.00" class="w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900">
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">Active</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700">History</span>
                            <span class="rounded-full bg-orange-100 px-2 py-0.5 text-orange-700">Access Restricted</span>
                            <span class="rounded-full bg-purple-100 px-2 py-0.5 text-purple-700">COA Rules Applied</span>
                        </div>
                    </section>
                </div>

                <div x-show="templateAccessRestricted" class="mt-4 rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-800">You are not authorized to create templates for this account. <span class="font-semibold">Access Restricted</span></div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                    <span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Active (COA Rules Applied)</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="closeModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="button" :disabled="templateAccessRestricted" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-blue-300">Create Template</button>
                    </div>
                </div>
            </section>
        </div>
    </x-loan.page>

    <script>
        function journalCommandCenter(accountOptionsInput) {
            const fallbackAccounts = [
                { id: 1, code: '4099', name: 'M-Pesa Utility', restricted: false, floor: 5000, starting_balance: 52000 },
                { id: 2, code: '4021', name: 'Cash Account', restricted: false, floor: 5000, starting_balance: 9500 },
                { id: 3, code: '61001', name: 'Equity Bank', restricted: false, floor: 10000, starting_balance: 120000 },
                { id: 4, code: '70010', name: 'Director Equity', restricted: true, floor: 0, starting_balance: 200000 },
            ];
            const accountOptions = Array.isArray(accountOptionsInput) && accountOptionsInput.length > 0 ? accountOptionsInput : fallbackAccounts;
            return {
                accountOptions,
                templateModalOpen: false,
                templateDebitId: '',
                templateCreditId: '',
                rows: [],
                activities: [
                    { id: 1, time: '10:45 AM', title: 'KSh 1.2M M-Pesa to Bank', status: 'Posted', amount: 'KSh 1,200,000' },
                    { id: 2, time: '09:55 AM', title: 'Salary adjustment posted', status: 'Pending Approval', amount: 'KSh 180,000' },
                    { id: 3, time: '09:05 AM', title: 'Loan recovery reversal draft saved', status: 'Blocked Draft', amount: 'KSh 42,500' },
                    { id: 4, time: '08:40 AM', title: 'Batch charges allocation', status: 'Posted', amount: 'KSh 2,350' },
                    { id: 5, time: '08:15 AM', title: 'Suspense account clearance', status: 'Pending Approval', amount: 'KSh 95,000' },
                ],
                init() {
                    const tableRows = document.querySelectorAll('#journal-lines-body tr');
                    this.rows = Array.from(tableRows).map((_, i) => ({ accountId: null, debit: 0, credit: 0, projectedBalance: 0, floor: 0, belowFloor: false, restricted: false }));
                    this.rows.forEach((_, i) => this.syncRow(i));

                    const addBtn = document.getElementById('add-line-btn');
                    if (addBtn) {
                        addBtn.addEventListener('click', () => {
                            const tbody = document.getElementById('journal-lines-body');
                            const next = Number(tbody.dataset.nextIndex || this.rows.length);
                            const accountOptionsHtml = this.accountOptions.map((opt) => `<option value="${opt.id}">${opt.code} · ${opt.name}</option>`).join('');
                            tbody.insertAdjacentHTML('beforeend', `<tr><td class="px-3 py-2"><select name="lines[${next}][accounting_chart_account_id]" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" x-model.number="rows[${next}].accountId" @change="syncRow(${next})"><option value="">—</option>${accountOptionsHtml}</select></td><td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="lines[${next}][debit]" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td><td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" name="lines[${next}][credit]" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td><td class="px-3 py-2"><input type="text" name="lines[${next}][memo]" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></td><td class="px-3 py-2 text-right"><span class="text-xs text-slate-500">KSh 0.00</span></td><td class="px-3 py-2 text-center"><svg class="mx-auto h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></td></tr>`);
                            tbody.dataset.nextIndex = String(next + 1);
                            this.rows.push({ accountId: null, debit: 0, credit: 0, projectedBalance: 0, floor: 0, belowFloor: false, restricted: false });
                        });
                    }
                },
                get totalDebit() { return this.rows.reduce((sum, r) => sum + (Number(r.debit) || 0), 0); },
                get totalCredit() { return this.rows.reduce((sum, r) => sum + (Number(r.credit) || 0), 0); },
                get hasFloorViolation() { return this.rows.some((r) => r.belowFloor); },
                get requiresApproval() { return this.rows.some((r) => r.restricted) || this.totalDebit >= 100000; },
                get attachmentRequired() { return this.requiresApproval; },
                get lowestProjectedBalance() { return this.rows.reduce((min, r) => Math.min(min, Number(r.projectedBalance) || 0), this.rows[0]?.projectedBalance || 0); },
                get lowestFloor() { return this.rows.reduce((min, r) => r.floor > 0 ? Math.min(min, r.floor) : min, Number.MAX_SAFE_INTEGER) === Number.MAX_SAFE_INTEGER ? 0 : this.rows.reduce((min, r) => r.floor > 0 ? Math.min(min, r.floor) : min, Number.MAX_SAFE_INTEGER); },
                get templateAccessRestricted() {
                    const d = this.accountOptions.find((a) => Number(a.id) === Number(this.templateDebitId));
                    const c = this.accountOptions.find((a) => Number(a.id) === Number(this.templateCreditId));
                    return Boolean(d?.restricted || c?.restricted);
                },
                syncRow(i) {
                    const row = this.rows[i];
                    if (!row) return;
                    const account = this.accountOptions.find((a) => Number(a.id) === Number(row.accountId));
                    if (!account) return;
                    row.floor = Number(account.floor) || 0;
                    row.restricted = Boolean(account.restricted);
                    row.projectedBalance = (Number(account.starting_balance) || 0) + (Number(row.debit) || 0) - (Number(row.credit) || 0);
                    row.belowFloor = row.floor > 0 && row.projectedBalance < row.floor;
                },
                formatKsh(value) { return `KSh ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; },
                openModal() { this.templateModalOpen = true; },
                closeModal() { this.templateModalOpen = false; },
            };
        }
    </script>
</x-loan-layout>
