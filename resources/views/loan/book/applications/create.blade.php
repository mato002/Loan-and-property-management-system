<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        @php
            $mapped = $loanFormMappedFields ?? [];
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div
            class="grid max-w-7xl grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
            x-data="loanProductPicker(
                @js($productMetaByName ?? []),
                @js((string) old('term_unit', $draftApplication?->term_unit ?? '')),
                @js((string) old('suspense_payment_id', data_get($draftApplication?->form_meta, 'fee_selected_payment_id', ''))),
                @js((int) (($pendingDrafts ?? collect())->count()))
            )"
            data-products-store-url="{{ route('loan.book.applications.products.store') }}"
            data-suspense-options-url="{{ route('loan.book.applications.suspense_options') }}"
        >
            @php($draftId = (int) old('draft_id', $draftApplication?->id ?? 0))
            <div class="xl:col-span-2 rounded-xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm" x-show="pendingDraftCount > 0">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-amber-900">Pending fee drafts</h3>
                        <p class="text-xs text-amber-700">
                            <span x-text="pendingDraftCount"></span>
                            application draft(s) need fee fulfillment before complete save.
                        </p>
                    </div>
                    <a href="#pending-drafts" class="inline-flex items-center rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">View drafts</a>
                </div>
                @if(($pendingDrafts ?? collect())->isNotEmpty())
                    <div id="pending-drafts" class="mt-3 max-h-44 space-y-2 overflow-y-auto pr-1">
                        @foreach($pendingDrafts as $draft)
                            <a
                                href="{{ route('loan.book.applications.create', ['draft_id' => $draft->id]) }}"
                                class="flex items-center justify-between rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-amber-50"
                            >
                                <span>{{ $draft->reference }} · {{ $draft->loanClient?->full_name ?? 'Unknown client' }}</span>
                                <span class="font-semibold text-amber-700">Resume</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <form method="post" action="{{ route('loan.book.applications.store') }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white px-5 py-6 shadow-sm space-y-4">
                @csrf
                <input type="hidden" name="draft_id" value="{{ $draftId > 0 ? $draftId : '' }}">
                <input type="hidden" name="save_as_draft" x-model="saveAsDraft">
                <div>
                    <label for="loan_client_id" class="block text-xs font-semibold text-slate-600 mb-1">Client</label>
                    <select
                        id="loan_client_id"
                        name="loan_client_id"
                        required
                        x-model="selectedClientId"
                        class="w-full rounded-lg border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm"
                        @change="autofillFromSelectedClient()"
                    >
                        <option value="">Select client…</option>
                        @foreach ($clients as $c)
                            <option
                                value="{{ $c->id }}"
                                data-full-name="{{ trim((string) $c->full_name) }}"
                                data-phone="{{ trim((string) ($c->phone ?? '')) }}"
                                data-id-number="{{ trim((string) ($c->id_number ?? '')) }}"
                                data-address="{{ trim((string) ($c->address ?? '')) }}"
                                data-branch="{{ trim((string) ($c->branch ?? '')) }}"
                                data-loan-officer="{{ trim((string) ($c->assignedEmployee?->full_name ?? '')) }}"
                                data-guarantor-full-name="{{ trim((string) ($c->guarantor_1_full_name ?? '')) }}"
                                data-guarantor-id-number="{{ trim((string) ($c->guarantor_1_id_number ?? '')) }}"
                                data-guarantor-phone="{{ trim((string) ($c->guarantor_1_phone ?? '')) }}"
                                data-search="{{ strtolower(trim((string) ($c->full_name.' '.$c->client_number.' '.($c->phone ?? '').' '.($c->id_number ?? '')))) }}"
                                @selected((string) old('loan_client_id', $draftApplication?->loan_client_id ?? $selectedClientId ?? '') === (string) $c->id)
                            >{{ $c->full_name }} · {{ $c->client_number }}</option>
                        @endforeach
                    </select>
                    @error('loan_client_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @if (isset($mapped['product_name']))
                <div>
                    <label for="product_name" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['product_name']['label'] ?? 'Product' }}</label>
                    <div class="flex gap-2">
                        <select id="product_name" name="product_name" required class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select product...</option>
                            @foreach (($productOptions ?? []) as $productName)
                                <option value="{{ $productName }}" @selected(old('product_name', $draftApplication?->product_name ?? $defaultProductName ?? '') === $productName)>{{ $productName }}</option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-bold text-slate-700 transition-colors hover:bg-slate-50"
                            title="Create new product"
                            @click="openProductModal"
                        >+</button>
                    </div>
                    @error('product_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500" x-text="selectedProductHint"></p>
                </div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if (isset($mapped['amount_requested']))
                    <div>
                        <label for="amount_requested" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['amount_requested']['label'] ?? 'Amount requested' }}</label>
                        <input id="amount_requested" name="amount_requested" type="number" step="0.01" min="0" value="{{ old('amount_requested', $draftApplication?->amount_requested) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount_requested')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    @if (isset($mapped['term_unit']))
                    <div>
                        <label for="term_unit" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['term_unit']['label'] ?? 'Term unit' }}</label>
                        <select id="term_unit" name="term_unit" required class="w-full rounded-lg border-slate-200 text-sm" x-model="termUnit" @change="onTermUnitChange()">
                            <option value="" @selected(old('term_unit', $draftApplication?->term_unit ?? '') === '')>Select term unit…</option>
                            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('term_unit', $draftApplication?->term_unit ?? '') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500" x-show="termUnit === ''">Choose how the loan term is measured (days, weeks, or months).</p>
                        @error('term_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    @if (isset($mapped['term_value']))
                    <div>
                        <label for="term_value" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['term_value']['label'] ?? 'Term length' }}</label>
                        <input id="term_value" name="term_value" type="number" min="1" value="{{ old('term_value', $draftApplication?->term_value) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" placeholder="e.g. 6" />
                        @error('term_value')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    @if (isset($mapped['interest_rate']))
                    <div>
                        <label for="interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['interest_rate']['label'] ?? 'Interest rate (%)' }}</label>
                        <input id="interest_rate" name="interest_rate" type="number" step="0.0001" min="0" max="1000" value="{{ old('interest_rate', $draftApplication?->interest_rate) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('interest_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    @if (isset($mapped['interest_rate_period']))
                    <div class="sm:col-span-2">
                        <label for="interest_rate_period" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['interest_rate_period']['label'] ?? 'Interest period' }}</label>
                        <select id="interest_rate_period" name="interest_rate_period" class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['daily' => 'Per day', 'weekly' => 'Per week', 'monthly' => 'Per month', 'annual' => 'Per year'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('interest_rate_period', $draftApplication?->interest_rate_period ?? 'annual') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('interest_rate_period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                </div>
                @if (isset($mapped['stage']))
                <div>
                    <label for="stage" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['stage']['label'] ?? 'Stage' }}</label>
                    <select id="stage" name="stage" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($stages as $value => $label)
                            @continue($value === \App\Models\LoanBookApplication::STAGE_DISBURSED)
                            <option value="{{ $value }}" @selected(old('stage', $draftApplication?->stage ?? \App\Models\LoanBookApplication::STAGE_SUBMITTED) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">Disbursed is set automatically after a completed disbursement record.</p>
                    @error('stage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @endif
                @if (isset($mapped['branch']))
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['branch']['label'] ?? 'Branch (optional)' }}</label>
                    <div class="flex gap-2">
                        <select id="branch" name="branch" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select branch...</option>
                            @foreach (($branchOptions ?? []) as $branchName)
                                <option value="{{ $branchName }}" @selected(old('branch', $draftApplication?->branch) === $branchName)>{{ $branchName }}</option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-bold text-slate-700 transition-colors hover:bg-slate-50"
                            title="Create branch"
                            @click="openBranchModal"
                        >+</button>
                    </div>
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @endif
                @if (isset($mapped['purpose']))
                <div>
                    <label for="purpose" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['purpose']['label'] ?? 'Purpose' }}</label>
                    <textarea id="purpose" name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose', $draftApplication?->purpose ?? $defaultPurpose ?? '') }}</textarea>
                    @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @endif

                @if (isset($mapped['notes']))
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['notes']['label'] ?? 'Internal notes' }}</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $draftApplication?->notes) }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @endif
                <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-indigo-900">Required pre-booking fees</h4>
                        <span class="text-xs font-semibold text-indigo-700" x-text="requiredFeeBadge"></span>
                    </div>
                    <p class="text-xs text-indigo-800">
                        Charges for <strong>application</strong>, <strong>loan booking</strong>, and <strong>disbursement</strong> are enforced here.
                    </p>
                    <template x-if="requiredFeeBreakdown.length > 0">
                        <div class="space-y-1 text-xs text-slate-700">
                            <template x-for="fee in requiredFeeBreakdown" :key="fee.key">
                                <p>
                                    <span class="font-semibold text-slate-800" x-text="fee.name"></span>
                                    · <span x-text="fee.stageLabel"></span>
                                    · <span x-text="fee.amountLabel"></span>
                                </p>
                            </template>
                        </div>
                    </template>
                    <div>
                        <label for="suspense_payment_id" class="block text-xs font-semibold text-slate-700 mb-1">Attach one suspense payment</label>
                        <select id="suspense_payment_id" name="suspense_payment_id" class="w-full rounded-lg border-slate-200 text-sm" x-model="selectedSuspensePaymentId" @change="onSuspenseSelectionChange()">
                            <option value="">Select suspense payment...</option>
                            <template x-for="option in suspenseOptions" :key="option.id">
                                <option :value="String(option.id)" x-text="option.label"></option>
                            </template>
                        </select>
                        @error('suspense_payment_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-slate-600" x-text="suspenseCoverageHint"></p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" @click="saveAsDraft='1'" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Save draft</button>
                    <button type="submit" @click="saveAsDraft='0'" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save application</button>
                </div>
            </form>
            <aside class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm xl:sticky xl:top-4" x-data="{ mobileClientPreviewOpen: false }">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-md text-left xl:cursor-default"
                    @click="if (window.innerWidth < 1280) mobileClientPreviewOpen = !mobileClientPreviewOpen"
                >
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Selected client profile</h4>
                    <span class="text-xs font-semibold text-slate-500 xl:hidden" x-text="mobileClientPreviewOpen ? 'Hide' : 'Show'"></span>
                </button>
                <div class="mt-2 text-xs text-slate-500 xl:hidden">
                    <span class="font-semibold text-slate-600">Client:</span>
                    <span x-text="selectedClientPreview.fullName || 'None selected'"></span>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-700" x-show="mobileClientPreviewOpen || window.innerWidth >= 1280" x-cloak>
                    <p><span class="font-semibold text-slate-600">Name:</span> <span x-text="selectedClientPreview.fullName || '—'"></span></p>
                    <p><span class="font-semibold text-slate-600">Phone:</span> <span x-text="selectedClientPreview.phone || '—'"></span></p>
                    <p><span class="font-semibold text-slate-600">ID No.:</span> <span x-text="selectedClientPreview.idNumber || '—'"></span></p>
                    <p><span class="font-semibold text-slate-600">Address:</span> <span x-text="selectedClientPreview.address || '—'"></span></p>
                    @if (isset($mapped['loan_officer']))
                        <p><span class="font-semibold text-slate-600">{{ $mapped['loan_officer']['label'] }}:</span> <span x-text="selectedClientPreview.loanOfficer || '—'"></span></p>
                    @endif
                    @if (isset($mapped['client_id_number']))
                        <p><span class="font-semibold text-slate-600">{{ $mapped['client_id_number']['label'] }}:</span> <span x-text="selectedClientPreview.idNumber || '—'"></span></p>
                    @endif
                </div>
                <div class="mt-4 border-t border-slate-200 pt-3" x-show="mobileClientPreviewOpen || window.innerWidth >= 1280" x-cloak>
                    <h5 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Application draft</h5>
                    <div class="mt-2 space-y-1 text-xs text-slate-700">
                        <template x-if="applicationPreviewFields.length === 0 && applicationPreviewImages.length === 0">
                            <p class="text-slate-500">Fill the form to preview entered details here.</p>
                        </template>
                        <template x-for="item in applicationPreviewFields" :key="item.key">
                            <p>
                                <span class="font-semibold text-slate-600" x-text="item.label + ':'"></span>
                                <span x-text="item.value"></span>
                            </p>
                        </template>
                    </div>
                    <div class="mt-3 space-y-2" x-show="applicationPreviewImages.length > 0">
                        <h6 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Uploaded images</h6>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="image in applicationPreviewImages" :key="image.key">
                                <figure class="overflow-hidden rounded-md border border-slate-200 bg-slate-50 p-1">
                                    <img :src="image.url" :alt="image.label" class="h-20 w-full rounded object-cover" />
                                    <figcaption class="mt-1 truncate text-[10px] text-slate-600" x-text="image.label"></figcaption>
                                </figure>
                            </template>
                        </div>
                    </div>
                </div>
            </aside>

            <div
                x-show="showProductModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
                @keydown.escape.window="closeProductModal"
                @click.self="closeProductModal"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-800">Create loan product</h3>
                    <p class="mt-1 text-xs text-slate-500">Save a product and immediately select it for this application.</p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label for="new_product_name" class="block text-xs font-semibold text-slate-600 mb-1">Product name</label>
                            <input id="new_product_name" x-model.trim="newProductName" type="text" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. Salary advance" />
                        </div>
                        <div>
                            <label for="new_product_description" class="block text-xs font-semibold text-slate-600 mb-1">Description (optional)</label>
                            <textarea id="new_product_description" x-model.trim="newProductDescription" rows="3" class="w-full rounded-lg border-slate-200 text-sm"></textarea>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="new_product_interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">Default interest value (optional)</label>
                                <input id="new_product_interest_rate" x-model.trim="newProductInterestRate" type="number" step="0.0001" min="0" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="new_product_interest_rate_type" class="block text-xs font-semibold text-slate-600 mb-1">Default interest type</label>
                                <select id="new_product_interest_rate_type" x-model="newProductInterestRateType" class="w-full rounded-lg border-slate-200 text-sm">
                                    <option value="percent">Percentage (%)</option>
                                    <option value="fixed">Fixed amount</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="new_product_interest_rate_period" class="block text-xs font-semibold text-slate-600 mb-1">Default interest period</label>
                                <select id="new_product_interest_rate_period" x-model="newProductInterestRatePeriod" class="w-full rounded-lg border-slate-200 text-sm">
                                    <option value="daily">Per day</option>
                                    <option value="weekly">Per week</option>
                                    <option value="monthly">Per month</option>
                                    <option value="annual">Per year</option>
                                </select>
                            </div>
                            <div>
                                <label for="new_product_term_months" class="block text-xs font-semibold text-slate-600 mb-1">Default term length (optional)</label>
                                <input id="new_product_term_months" x-model.trim="newProductTermMonths" type="number" min="1" max="600" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="new_product_term_unit" class="block text-xs font-semibold text-slate-600 mb-1">Default term unit</label>
                                <select id="new_product_term_unit" x-model="newProductTermUnit" class="w-full rounded-lg border-slate-200 text-sm">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p x-show="productModalError" x-text="productModalError" class="mt-3 text-xs text-red-600"></p>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="closeProductModal">Cancel</button>
                        <button type="button" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040] disabled:cursor-not-allowed disabled:opacity-70" @click="saveNewProduct" :disabled="isSavingProduct">
                            <span x-show="!isSavingProduct">Save product</span>
                            <span x-show="isSavingProduct">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>

            <div
                x-show="showBranchModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
                @keydown.escape.window="closeBranchModal"
                @click.self="closeBranchModal"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-800">Create branch</h3>
                    <p class="mt-1 text-xs text-slate-500">Add a branch and use it immediately in this application.</p>

                    <div class="mt-4">
                        <label for="new_branch_name" class="block text-xs font-semibold text-slate-600 mb-1">Branch name</label>
                        <input id="new_branch_name" x-model.trim="newBranchName" type="text" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. Nairobi CBD" />
                    </div>

                    <p x-show="branchModalError" x-text="branchModalError" class="mt-3 text-xs text-red-600"></p>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="closeBranchModal">Cancel</button>
                        <button type="button" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040] disabled:cursor-not-allowed disabled:opacity-70" @click="saveNewBranch" :disabled="isSavingBranch">
                            <span x-show="!isSavingBranch">Save branch</span>
                            <span x-show="isSavingBranch">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    function loanProductPicker(productMetaByName = {}, initialTermUnit = '', initialSuspensePaymentId = '', pendingDraftCount = 0) {
        const productStoreUrl = @json(route('loan.book.applications.products.store'));
        const suspenseOptionsUrl = @json(route('loan.book.applications.suspense_options'));
        return {
            productMetaByName,
            termUnit: initialTermUnit ? String(initialTermUnit) : '',
            pendingDraftCount: Number(pendingDraftCount || 0),
            saveAsDraft: '0',
            selectedSuspensePaymentId: initialSuspensePaymentId ? String(initialSuspensePaymentId) : '',
            suspenseOptions: [],
            requiredFeeTotal: 0,
            requiredFeeBreakdown: [],
            suspenseCoverageHint: 'Select client and product to load required fees and eligible suspense payment.',
            showProductModal: false,
            newProductName: '',
            newProductDescription: '',
            newProductInterestRate: '',
            newProductInterestRateType: 'percent',
            newProductTermMonths: '',
            newProductTermUnit: 'monthly',
            newProductInterestRatePeriod: 'annual',
            productModalError: '',
            isSavingProduct: false,
            showBranchModal: false,
            newBranchName: '',
            branchModalError: '',
            isSavingBranch: false,
            lastAutoFilled: {},
            selectedClientPreview: {
                fullName: '',
                phone: '',
                idNumber: '',
                address: '',
                loanOfficer: '',
            },
            clientDropdownOpen: false,
            clientSearchQuery: '',
            clientOptions: [],
            filteredClientOptions: [],
            selectedClientId: '',
            selectedClientData: null,
            selectedClientLabel: '',
            selectedProductHint: '',
            applicationPreviewFields: [],
            applicationPreviewImages: [],
            previewImageUrls: [],
            init() {
                this.$nextTick(() => {
                    this.setupClientSearch();
                    this.setupApplicationPreview();
                    this.$watch('selectedClientId', () => this.autofillFromSelectedClient());
                    this.autofillFromSelectedClient();
                    this.applyProductDefaults();
                    this.refreshFeeAndSuspenseOptions();
                    const termUnitSelect = this.$el.querySelector('#term_unit');
                    if (termUnitSelect) {
                        this.termUnit = termUnitSelect.value || '';
                    }
                    const productSelect = this.$el.querySelector('#product_name');
                    productSelect?.addEventListener('change', () => {
                        this.applyProductDefaults();
                        this.refreshFeeAndSuspenseOptions();
                    });
                    const amountInput = this.$el.querySelector('#amount_requested');
                    amountInput?.addEventListener('change', () => this.refreshFeeAndSuspenseOptions());
                    amountInput?.addEventListener('input', () => this.refreshFeeAndSuspenseOptions());
                });
            },
            get requiredFeeBadge() {
                if (this.requiredFeeTotal <= 0) {
                    return 'No required fee';
                }
                return `Required total: ${this.requiredFeeTotal.toFixed(2)}`;
            },
            onSuspenseSelectionChange() {
                const selected = this.suspenseOptions.find((item) => String(item.id) === String(this.selectedSuspensePaymentId));
                if (!selected) {
                    if (this.requiredFeeTotal > 0) {
                        this.suspenseCoverageHint = `Select one suspense payment with amount >= ${this.requiredFeeTotal.toFixed(2)}.`;
                    }
                    return;
                }
                const delta = Number(selected.amount || 0) - Number(this.requiredFeeTotal || 0);
                if (delta >= 0) {
                    this.suspenseCoverageHint = `Fee covered. Excess to wallet: ${delta.toFixed(2)}.`;
                } else {
                    this.suspenseCoverageHint = `Short by ${Math.abs(delta).toFixed(2)}. Save as draft or pick another payment.`;
                }
            },
            async refreshFeeAndSuspenseOptions() {
                const clientId = String(this.selectedClientId || '').trim();
                const productName = String(this.$el.querySelector('#product_name')?.value || '').trim();
                const amountRequested = String(this.$el.querySelector('#amount_requested')?.value || '').trim();
                const draftId = String(this.$el.querySelector('input[name="draft_id"]')?.value || '').trim();
                if (!clientId) {
                    this.suspenseOptions = [];
                    this.requiredFeeBreakdown = [];
                    this.requiredFeeTotal = 0;
                    this.suspenseCoverageHint = 'Select a client to load suspense options.';
                    return;
                }
                try {
                    const params = new URLSearchParams({
                        loan_client_id: clientId,
                        product_name: productName,
                        amount_requested: amountRequested,
                        draft_id: draftId,
                    });
                    const response = await fetch(`${suspenseOptionsUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (!response.ok || !data?.ok) {
                        this.suspenseOptions = [];
                        this.requiredFeeBreakdown = [];
                        this.requiredFeeTotal = 0;
                        this.suspenseCoverageHint = 'Unable to load suspense options right now.';
                        return;
                    }
                    this.suspenseOptions = Array.isArray(data.options) ? data.options : [];
                    this.requiredFeeTotal = Number(data.required_fee_total || 0);
                    this.requiredFeeBreakdown = (Array.isArray(data.required_fee_breakdown) ? data.required_fee_breakdown : []).map((item, index) => ({
                        key: `${item.name || 'fee'}-${index}`,
                        name: String(item.name || 'Fee'),
                        stageLabel: String(item.stage || '').replace('_', ' '),
                        amountLabel: Number(item.computed_amount || 0).toFixed(2),
                    }));
                    const stillExists = this.suspenseOptions.some((item) => String(item.id) === String(this.selectedSuspensePaymentId));
                    if (!stillExists) {
                        this.selectedSuspensePaymentId = '';
                    }
                    this.onSuspenseSelectionChange();
                } catch (error) {
                    this.suspenseOptions = [];
                    this.requiredFeeBreakdown = [];
                    this.requiredFeeTotal = 0;
                    this.suspenseCoverageHint = 'Unable to load suspense options right now.';
                }
            },
            setupApplicationPreview() {
                const form = this.$el.querySelector('form');
                if (!form) return;
                const refresh = () => this.refreshApplicationPreview();
                form.addEventListener('input', refresh);
                form.addEventListener('change', refresh);
                this.refreshApplicationPreview();
            },
            refreshApplicationPreview() {
                const form = this.$el.querySelector('form');
                if (!form) return;

                const fields = [];
                const controls = Array.from(form.querySelectorAll('input, select, textarea'));
                for (const control of controls) {
                    const tagName = (control.tagName ?? '').toLowerCase();
                    const type = (control.type ?? '').toLowerCase();
                    const name = (control.name ?? '').trim();
                    const id = (control.id ?? '').trim();
                    if (!name || name === '_token' || name === 'loan_client_id') continue;
                    if (type === 'hidden' || type === 'file' || type === 'checkbox' || type === 'radio') continue;

                    let value = '';
                    if (tagName === 'select') {
                        value = (control.options?.[control.selectedIndex]?.textContent ?? '').trim();
                    } else {
                        value = String(control.value ?? '').trim();
                    }
                    if (!value || value.toLowerCase().startsWith('select ')) continue;

                    fields.push({
                        key: `${name}-${id || 'field'}`,
                        label: this.previewLabelForControl(control, name, id),
                        value,
                    });
                }
                this.applicationPreviewFields = fields;
                this.refreshApplicationImagePreview(form);
            },
            refreshApplicationImagePreview(form) {
                this.previewImageUrls.forEach((url) => URL.revokeObjectURL(url));
                this.previewImageUrls = [];

                const imageItems = [];
                const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
                for (const input of fileInputs) {
                    const files = Array.from(input.files ?? []);
                    if (files.length === 0) continue;
                    const label = this.previewLabelForControl(input, input.name ?? '', input.id ?? '');
                    files.forEach((file, index) => {
                        if (!String(file.type ?? '').toLowerCase().startsWith('image/')) return;
                        const url = URL.createObjectURL(file);
                        this.previewImageUrls.push(url);
                        imageItems.push({
                            key: `${input.name || input.id || 'file'}-${index}-${file.name}`,
                            label: files.length > 1 ? `${label} (${index + 1})` : label,
                            url,
                        });
                    });
                }
                this.applicationPreviewImages = imageItems;
            },
            previewLabelForControl(control, fallbackName, id) {
                if (id) {
                    const explicitLabel = this.$el.querySelector(`label[for="${id}"]`);
                    if (explicitLabel) {
                        const labelText = (explicitLabel.textContent ?? '').replace(/\s+/g, ' ').trim();
                        if (labelText !== '') return labelText;
                    }
                }
                const normalized = String(fallbackName ?? '')
                    .replace(/\[\]/g, '')
                    .replace(/\[/g, ' ')
                    .replace(/\]/g, '')
                    .replace(/_/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
                if (normalized === '') return 'Field';
                return normalized.charAt(0).toUpperCase() + normalized.slice(1);
            },
            setupClientSearch() {
                const clientSelect = this.$el.querySelector('#loan_client_id');
                if (!clientSelect) return;

                this.clientOptions = Array.from(clientSelect.options)
                    .filter((option) => option.value !== '')
                    .map((option) => ({
                        value: String(option.value),
                        label: (option.textContent ?? '').trim(),
                        search: ((option.dataset.search ?? option.textContent ?? '') + '').toLowerCase(),
                        fullName: (option.dataset.fullName ?? '').trim(),
                        phone: (option.dataset.phone ?? '').trim(),
                        idNumber: (option.dataset.idNumber ?? '').trim(),
                        address: (option.dataset.address ?? '').trim(),
                        branch: (option.dataset.branch ?? '').trim(),
                        guarantorFullName: (option.dataset.guarantorFullName ?? '').trim(),
                        guarantorIdNumber: (option.dataset.guarantorIdNumber ?? '').trim(),
                        guarantorPhone: (option.dataset.guarantorPhone ?? '').trim(),
                        loanOfficer: (option.dataset.loanOfficer ?? '').trim(),
                    }));

                this.selectedClientId = String(clientSelect.value ?? '');
                this.selectedClientData = this.clientOptions.find((item) => item.value === this.selectedClientId) ?? null;
                this.selectedClientLabel = this.selectedClientData?.label ?? '';
                this.clientSearchQuery = '';
                this.filterClientOptionsList();
            },
            selectedClientOption() {
                if (!this.selectedClientId) return null;
                return this.clientOptions.find((item) => item.value === String(this.selectedClientId)) ?? null;
            },
            openClientDropdown() {
                this.clientDropdownOpen = true;
                this.clientSearchQuery = '';
                this.filterClientOptionsList();
            },
            filterClientOptionsList() {
                const query = String(this.clientSearchQuery ?? '').trim().toLowerCase();
                this.filteredClientOptions = this.clientOptions.filter((option) => query === '' || option.search.includes(query));
            },
            pickFirstVisibleClient() {
                const firstVisible = this.filteredClientOptions[0] ?? null;
                if (!firstVisible) return;
                this.selectClientOption(firstVisible.value);
            },
            selectClientOption(value) {
                const clientSelect = this.$el.querySelector('#loan_client_id');
                const normalizedValue = String(value ?? '');
                if (!clientSelect || normalizedValue === '') return;

                const selectedOptionData = this.clientOptions.find((item) => item.value === normalizedValue) ?? null;
                if (!selectedOptionData) return;

                this.selectedClientId = normalizedValue;
                this.selectedClientData = selectedOptionData;
                this.selectedClientLabel = selectedOptionData.label;
                this.clientSearchQuery = '';
                this.clientDropdownOpen = false;
                clientSelect.value = normalizedValue;
                this.filterClientOptionsList();
                clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
            },
            onTermUnitChange() {
                const termInput = this.$el.querySelector('#term_value');
                if (!this.termUnit && termInput) {
                    termInput.value = '';
                }
            },
            openProductModal() {
                this.productModalError = '';
                this.showProductModal = true;
            },
            closeProductModal() {
                if (this.isSavingProduct) return;
                this.showProductModal = false;
                this.newProductName = '';
                this.newProductDescription = '';
                this.newProductInterestRate = '';
                this.newProductInterestRateType = 'percent';
                this.newProductTermMonths = '';
                this.newProductTermUnit = 'monthly';
                this.newProductInterestRatePeriod = 'annual';
                this.productModalError = '';
            },
            applyProductDefaults() {
                const productSelect = this.$el.querySelector('#product_name');
                const termInput = this.$el.querySelector('#term_value');
                const termUnitSelect = this.$el.querySelector('#term_unit');
                const interestInput = this.$el.querySelector('#interest_rate');
                const interestPeriodSelect = this.$el.querySelector('#interest_rate_period');
                const name = (productSelect?.value ?? '').trim();
                if (!name) {
                    this.selectedProductHint = '';
                    return;
                }

                const meta = this.productMetaByName[name] ?? null;
                if (!meta) {
                    this.selectedProductHint = '';
                    return;
                }

                const parts = [];
                if (meta.default_interest_rate !== null && meta.default_interest_rate !== undefined) {
                    const defaultInterestPeriod = String(meta.default_interest_rate_period ?? 'annual').toLowerCase();
                    const defaultInterestType = String(meta.default_interest_rate_type ?? 'percent').toLowerCase();
                    const interestPeriodLabel = {
                        daily: 'day',
                        weekly: 'week',
                        monthly: 'month',
                        annual: 'year',
                    }[defaultInterestPeriod] ?? defaultInterestPeriod;
                    const defaultInterestValue = defaultInterestType === 'percent'
                        ? `${Number(meta.default_interest_rate).toFixed(4)}%`
                        : Number(meta.default_interest_rate).toFixed(2);
                    parts.push(`Default interest: ${defaultInterestValue} per ${interestPeriodLabel}.`);
                    const currentRate = (interestInput?.value ?? '').trim();
                    if (interestInput && currentRate === '') {
                        interestInput.value = String(meta.default_interest_rate);
                        interestInput.dispatchEvent(new Event('input', { bubbles: true }));
                        interestInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (interestPeriodSelect && (interestPeriodSelect.value ?? '') === 'annual') {
                        interestPeriodSelect.value = defaultInterestPeriod;
                        interestPeriodSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                if (meta.default_term_months) {
                    const defaultTermUnit = String(meta.default_term_unit ?? 'monthly').toLowerCase();
                    parts.push(`Default term: ${meta.default_term_months} ${defaultTermUnit}.`);
                    const current = (termInput?.value ?? '').trim();
                    if (termInput && (current === '' || current === '12')) {
                        termInput.value = String(meta.default_term_months);
                        termInput.dispatchEvent(new Event('input', { bubbles: true }));
                        termInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (termUnitSelect && (!(termUnitSelect.value ?? '').trim() || (termUnitSelect.value ?? '') === 'monthly')) {
                        termUnitSelect.value = defaultTermUnit;
                        termUnitSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                if (meta.charges_summary) {
                    parts.push(`Charges: ${meta.charges_summary}.`);
                }
                const tu = this.$el.querySelector('#term_unit');
                if (tu) {
                    this.termUnit = tu.value || '';
                }
                this.selectedProductHint = parts.join(' ');
            },
            openBranchModal() {
                this.branchModalError = '';
                this.showBranchModal = true;
            },
            closeBranchModal() {
                if (this.isSavingBranch) return;
                this.showBranchModal = false;
                this.newBranchName = '';
                this.branchModalError = '';
            },
            clientDataset() {
                const selectedOption = this.selectedClientOption();
                if (!selectedOption || !selectedOption.value) {
                    return null;
                }

                return {
                    fullName: selectedOption.fullName ?? '',
                    phone: selectedOption.phone ?? '',
                    idNumber: selectedOption.idNumber ?? '',
                    address: selectedOption.address ?? '',
                    branch: selectedOption.branch ?? '',
                    guarantorFullName: selectedOption.guarantorFullName ?? '',
                    guarantorIdNumber: selectedOption.guarantorIdNumber ?? '',
                    guarantorPhone: selectedOption.guarantorPhone ?? '',
                    loanOfficer: selectedOption.loanOfficer ?? '',
                };
            },
            updateClientPreview(data) {
                this.selectedClientPreview = {
                    fullName: data?.fullName ?? '',
                    phone: data?.phone ?? '',
                    idNumber: data?.idNumber ?? '',
                    address: data?.address ?? '',
                    loanOfficer: data?.loanOfficer ?? '',
                };
            },
            setIfSafe(fieldId, nextValue) {
                const input = this.$el.querySelector(`#${fieldId}`);
                if (!input) return;

                const current = (input.value ?? '').trim();
                const previousAuto = (this.lastAutoFilled[fieldId] ?? '').trim();
                const candidate = (nextValue ?? '').trim();

                if (candidate === '') return;
                if (current !== '' && current !== previousAuto) return;

                input.value = candidate;
                this.lastAutoFilled[fieldId] = candidate;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
            autofillFromSelectedClient() {
                const data = this.clientDataset();
                const selectedOption = this.selectedClientOption();
                this.selectedClientLabel = selectedOption?.label ?? '';
                this.updateClientPreview(data);
                this.refreshFeeAndSuspenseOptions();
                if (!data) return;

                this.setIfSafe('branch', data.branch);
            },
            async saveNewProduct() {
                this.productModalError = '';
                if (!this.newProductName) {
                    this.productModalError = 'Product name is required.';
                    return;
                }
                if (!productStoreUrl || String(productStoreUrl).includes('/undefined')) {
                    this.productModalError = 'Product endpoint is invalid. Reload page and try again.';
                    return;
                }

                this.isSavingProduct = true;
                try {
                    const response = await fetch(productStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                        },
                        body: JSON.stringify({
                            name: this.newProductName,
                            description: this.newProductDescription,
                            default_interest_rate: this.newProductInterestRate,
                            default_interest_rate_type: this.newProductInterestRateType,
                            default_term_months: this.newProductTermMonths,
                            default_term_unit: this.newProductTermUnit,
                            default_interest_rate_period: this.newProductInterestRatePeriod,
                        }),
                    });

                    const data = await response.json();
                    if (!response.ok || !data?.ok || !data?.product?.name) {
                        this.productModalError = data?.message ?? 'Unable to save product. Please try again.';
                        return;
                    }

                    const productSelect = this.$el.querySelector('#product_name');
                    const existingOption = Array.from(productSelect.options).find((option) => option.value === data.product.name);
                    if (!existingOption) {
                        const option = new Option(data.product.name, data.product.name, true, true);
                        productSelect.add(option);
                    } else {
                        existingOption.selected = true;
                    }
                    this.productMetaByName[data.product.name] = {
                        default_interest_rate: data.product.default_interest_rate ?? null,
                        default_interest_rate_type: data.product.default_interest_rate_type ?? 'percent',
                        default_term_months: data.product.default_term_months ?? null,
                        default_term_unit: data.product.default_term_unit ?? 'monthly',
                        default_interest_rate_period: data.product.default_interest_rate_period ?? 'annual',
                    };
                    productSelect.dispatchEvent(new Event('change'));
                    this.closeProductModal();
                } catch (error) {
                    this.productModalError = 'Unable to save product. Please check your connection.';
                } finally {
                    this.isSavingProduct = false;
                }
            },
            async saveNewBranch() {
                this.branchModalError = '';
                if (!this.newBranchName) {
                    this.branchModalError = 'Branch name is required.';
                    return;
                }

                this.isSavingBranch = true;
                try {
                    const response = await fetch(@json(route('loan.clients.branches.store')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                        },
                        body: JSON.stringify({ name: this.newBranchName }),
                    });

                    const data = await response.json();
                    if (!response.ok || !data?.ok || !data?.branch?.name) {
                        this.branchModalError = data?.message ?? 'Unable to save branch. Please try again.';
                        return;
                    }

                    const branchSelect = this.$el.querySelector('#branch');
                    const existingOption = Array.from(branchSelect.options).find((option) => option.value === data.branch.name);
                    if (!existingOption) {
                        const option = new Option(data.branch.name, data.branch.name, true, true);
                        branchSelect.add(option);
                    } else {
                        existingOption.selected = true;
                    }
                    branchSelect.dispatchEvent(new Event('change'));
                    this.closeBranchModal();
                } catch (error) {
                    this.branchModalError = 'Unable to save branch. Please check your connection.';
                } finally {
                    this.isSavingBranch = false;
                }
            },
        };
    }
</script>
