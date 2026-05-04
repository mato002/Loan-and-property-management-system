<x-loan-layout>
    <x-loan.page
        title="Quick lead capture"
        subtitle="Log a prospect in seconds. Full KYC happens when you convert to a client."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.leads') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to leads
            </a>
        </x-slot>

        <x-slot name="banner">
            @include('loan.clients.partials.identity-flashes')
        </x-slot>

        <div class="max-w-6xl mx-auto grid grid-cols-1 gap-8 lg:grid-cols-3 lg:items-start">
            {{-- Main column --}}
            <div class="lg:col-span-2 space-y-6">
                <form method="post" action="{{ route('loan.clients.leads.store') }}" class="space-y-6" id="lead-quick-capture-form">
                    @csrf

                    {{-- Lead Identity --}}
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-4">Lead identity</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 text-xs text-slate-600">
                                <span class="font-medium text-slate-700">Lead reference</span>
                                <span class="block font-mono text-slate-500 mt-0.5">Auto-assigned when you save</span>
                            </div>
                            <div>
                                <x-input-label for="first_name" value="First name *" />
                                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required autocomplete="given-name" />
                                <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                            </div>
                            <div>
                                <x-input-label for="last_name" value="Last name *" />
                                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required autocomplete="family-name" />
                                <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="phone" value="Phone number *" />
                                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" required autocomplete="tel" inputmode="tel" placeholder="e.g. 07… or 254…" />
                                <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">
                                    Phone is used for duplicate checks, follow-up, SMS campaigns, and future client matching.
                                </p>
                                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="email" value="Email (optional)" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" autocomplete="email" />
                                <x-input-error class="mt-2" :messages="$errors->get('email')" />
                            </div>
                        </div>
                    </section>

                    {{-- Source & ownership --}}
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-4">Lead source &amp; ownership</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <x-input-label for="assigned_employee_id" value="Assigned loan officer / staff *" />
                                <select id="assigned_employee_id" name="assigned_employee_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Select officer —</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) old('assigned_employee_id', $defaultAssignedEmployeeId ?? '') === (string) $employee->id)>{{ $employee->full_name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('assigned_employee_id')" />
                            </div>
                            <div class="sm:col-span-2">
                                @include('loan.clients.partials.branch-select-with-modal', [
                                    'fieldId' => 'branch',
                                    'selectedValue' => old('branch'),
                                    'branchOptions' => ($branchOptions ?? []),
                                    'storeUrl' => route('loan.clients.branches.store'),
                                ])
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="lead_source" value="Lead source *" />
                                <select id="lead_source" name="lead_source" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Select source —</option>
                                    @foreach (($leadSources ?? []) as $value => $label)
                                        <option value="{{ $value }}" @selected(old('lead_source') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('lead_source')" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="expected_loan_amount" value="Expected loan amount (optional)" />
                                <x-text-input id="expected_loan_amount" name="expected_loan_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('expected_loan_amount')" placeholder="0.00" />
                                <p class="mt-1 text-xs text-slate-500">Used for pipeline value and high-value idle alerts.</p>
                                <x-input-error class="mt-2" :messages="$errors->get('expected_loan_amount')" />
                            </div>
                        </div>
                    </section>

                    {{-- Activity / occupation --}}
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-4">Client activity / occupation</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <x-input-label for="occupation" value="Occupation / business activity (optional)" />
                                <x-text-input id="occupation" name="occupation" type="text" class="mt-1 block w-full" :value="old('occupation')" placeholder="e.g. Runs a kiosk, teacher, rideshare…" />
                                <x-input-error class="mt-2" :messages="$errors->get('occupation')" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="sector" value="Sector / industry *" />
                                <select id="sector" name="sector" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Select sector —</option>
                                    @foreach (($leadSectors ?? []) as $value => $label)
                                        <option value="{{ $value }}" @selected(old('sector') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('sector')" />
                            </div>
                        </div>
                    </section>

                    {{-- Status & follow-up --}}
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-4">Lead status &amp; follow-up</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="lead_status" value="Lead status" />
                                <select id="lead_status" name="lead_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ([
                                        'new' => 'New',
                                        'contacted' => 'Contacted',
                                        'qualified' => 'Qualified',
                                        'not_qualified' => 'Not qualified',
                                        'lost' => 'Lost',
                                    ] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('lead_status', 'new') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('lead_status')" />
                            </div>
                            <div>
                                <x-input-label for="follow_up_date" value="Follow-up date (optional)" />
                                <input type="date" id="follow_up_date" name="follow_up_date" value="{{ old('follow_up_date') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <x-input-error class="mt-2" :messages="$errors->get('follow_up_date')" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="follow_up_notes" value="Follow-up notes (optional)" />
                                <textarea id="follow_up_notes" name="follow_up_notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Next step, callback window, product interest…">{{ old('follow_up_notes') }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('follow_up_notes')" />
                            </div>
                        </div>
                    </section>

                    {{-- Notes --}}
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-4">Notes</h2>
                        <x-input-label for="notes" value="General notes (optional)" />
                        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Add quick notes about the lead, client activity, loan interest, referral details, or follow-up plan.">{{ old('notes') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </section>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <x-primary-button type="submit">{{ __('Save lead') }}</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Side panel --}}
            <aside class="space-y-4 lg:sticky lg:top-4">
                <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-teal-900">Lead capture tips</h3>
                    <ul class="mt-3 list-disc pl-4 space-y-2 text-xs text-teal-900/90 leading-relaxed">
                        <li>Use the prospect’s <strong>main mobile number</strong> whenever possible.</li>
                        <li>Pick the <strong>closest matching source</strong>—you can refine later.</li>
                        <li>Set a <strong>follow-up date</strong> when you owe a callback or visit.</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Why phone matters</h3>
                    <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                        The phone field powers duplicate detection today and will support SMS/WhatsApp outreach, activation campaigns, and matching when this lead becomes a full client.
                    </p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-amber-950">Analytics</h3>
                    <p class="mt-2 text-xs text-amber-950/90 leading-relaxed">
                        This lead will be used in staff performance, conversion, and campaign reports. Source, officer, branch, sector, and dates are stored for MTD rollups and funnel metrics.
                    </p>
                    <p class="mt-3 text-[11px] text-amber-900/80 leading-relaxed border-t border-amber-200/80 pt-3">
                        Structured capture fields are stored on the lead record (JSON) for reporting. Dedicated database columns can be added later if you need heavy SQL indexing on individual attributes.
                    </p>
                </div>
            </aside>
        </div>
    </x-loan.page>
</x-loan-layout>
