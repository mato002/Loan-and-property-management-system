<x-loan-layout>
    <x-loan.page
        title="Send or schedule SMS"
        subtitle="Recipients are charged from the SMS wallet when messages are sent (immediate or when a schedule runs)."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.wallet') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Wallet: {{ number_format((float) $walletBalance, 2) }} {{ $currency }}
            </a>
            <a href="{{ route('loan.bulksms.templates.index') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Manage templates
            </a>
        </x-slot>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                    <form method="post" action="{{ route('loan.bulksms.compose.store') }}" class="space-y-5">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="recipient_source" class="block text-sm font-medium text-slate-700">Recipient source</label>
                                <select id="recipient_source" name="recipient_source"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">
                                    <option value="manual" @selected(old('recipient_source', 'manual') === 'manual')>Manual phone numbers</option>
                                    <option value="all_clients" @selected(old('recipient_source') === 'all_clients')>All clients & leads</option>
                                    <option value="all_employees" @selected(old('recipient_source') === 'all_employees')>All employees</option>
                                    <option value="selected_contacts" @selected(old('recipient_source') === 'selected_contacts')>Select specific contacts</option>
                                </select>
                            </div>
                        </div>
                        <div id="contacts-selection-wrap" class="hidden">
                            <div id="contacts-list-wrap" class="mt-3">
                                <label for="contact_keys" class="block text-sm font-medium text-slate-700">Choose contacts</label>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <button type="button" id="contacts-select-all" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Select all</button>
                                    <button type="button" id="contacts-clear-selection" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear selection</button>
                                </div>
                                <select id="contact_keys" name="contact_keys[]" multiple
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f] min-h-[160px]">
                                    @foreach (($contactOptions ?? collect()) as $contact)
                                        <option
                                            value="{{ $contact['key'] }}"
                                            data-contact-type="{{ str_starts_with($contact['key'], 'employee:') ? 'employee' : 'client' }}"
                                            data-phone="{{ $contact['phone'] }}"
                                            @selected(collect(old('contact_keys', []))->contains($contact['key']))
                                        >
                                            {{ $contact['name'] }} · {{ $contact['meta'] }} ({{ $contact['phone'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-slate-500 mt-1">Click any contact to toggle selection. Use Select all / Clear selection for quick edits.</p>
                                @error('contact_keys')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div>
                            <label for="recipients" class="block text-sm font-medium text-slate-700">Recipients</label>
                            <p class="text-xs text-slate-500 mt-0.5">One number per line, or comma / semicolon separated. Digits only (9+ per number).</p>
                            <textarea id="recipients" name="recipients" rows="6" required
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">{{ old('recipients') }}</textarea>
                            @error('recipients')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <span class="font-medium">Recipient preview:</span>
                            <span id="recipient-preview">0 phone number(s) selected.</span>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-slate-700">Message</label>
                            <textarea id="message" name="message" rows="5" maxlength="1000" required
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">{{ old('message', $prefillBody) }}</textarea>
                            @error('message')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="sms_template_id" class="block text-sm font-medium text-slate-700">Link template (optional)</label>
                                <select id="sms_template_id" name="sms_template_id"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">
                                    <option value="">— None —</option>
                                    @foreach ($templates as $t)
                                        <option
                                            value="{{ $t->id }}"
                                            data-template-body="{{ e((string) $t->body) }}"
                                            @selected((string) old('sms_template_id', $prefillTemplateId) === (string) $t->id)
                                        >{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-slate-500 mt-1">For reporting only; paste or edit the message above.</p>
                            </div>
                            <div>
                                <label for="schedule_at" class="block text-sm font-medium text-slate-700">Schedule send (optional)</label>
                                <input type="datetime-local" id="schedule_at" name="schedule_at" value="{{ old('schedule_at') }}"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                                @error('schedule_at')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-slate-500 mt-1">Leave empty to send now (wallet charged immediately).</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                                Submit
                            </button>
                            <a href="{{ route('loan.bulksms.logs') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                                View logs
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-800">Pricing</h2>
                    <p class="text-sm text-slate-600 mt-2">
                        <span class="tabular-nums font-medium text-slate-900">{{ number_format($costPerSms, 2) }} {{ $currency }}</span>
                        per SMS segment (configurable via <code class="text-xs bg-slate-100 px-1 rounded">BULKSMS_COST_PER_SMS</code>).
                    </p>
                    <p class="text-xs text-slate-500 mt-3">Delivery is recorded in-app. Connect a real SMS provider in code when you are ready.</p>
                </div>
                <div class="bg-[#2f4f4f]/5 border border-[#2f4f4f]/20 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-[#264040]">Start from template</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        @forelse ($templates->take(8) as $t)
                            <li>
                                <a href="{{ route('loan.bulksms.compose', ['template' => $t->id]) }}" class="text-indigo-600 hover:underline">{{ $t->name }}</a>
                            </li>
                        @empty
                            <li class="text-slate-500">No templates yet. <a href="{{ route('loan.bulksms.templates.create') }}" class="text-indigo-600 font-medium hover:underline">Create one</a>.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <script>
            (() => {
                const source = document.getElementById('recipient_source');
                const contactsSelectionWrap = document.getElementById('contacts-selection-wrap');
                const contactsSelect = document.getElementById('contact_keys');
                const contactsSelectAllBtn = document.getElementById('contacts-select-all');
                const contactsClearBtn = document.getElementById('contacts-clear-selection');
                const recipients = document.getElementById('recipients');
                const templateSelect = document.getElementById('sms_template_id');
                const messageInput = document.getElementById('message');
                const preview = document.getElementById('recipient-preview');

                if (!source || !recipients) {
                    return;
                }

                let manualDraft = String(recipients.value || '');

                const parseManualCount = () => {
                    const raw = String(recipients.value || '');
                    const parts = raw.split(/[\r\n,;]+/).map((x) => x.trim()).filter(Boolean);
                    const normalized = new Set();
                    for (const part of parts) {
                        const digits = part.replace(/\D+/g, '');
                        if (digits.length >= 9) {
                            normalized.add(digits);
                        }
                    }
                    return normalized.size;
                };

                const visibleContactOptions = () => {
                    if (!contactsSelect) {
                        return [];
                    }
                    return [...contactsSelect.options].filter((opt) => !opt.hidden);
                };

                const selectedVisibleContactOptions = () => {
                    return visibleContactOptions().filter((opt) => opt.selected);
                };

                const normalizedPhoneSet = (phones) => {
                    const bag = new Set();
                    for (const raw of phones) {
                        const digits = String(raw || '').replace(/\D+/g, '');
                        if (digits.length >= 9) {
                            bag.add(digits);
                        }
                    }
                    return bag;
                };

                const autoDerivedPhoneSet = (src) => {
                    const all = visibleContactOptions();
                    if (src === 'all_clients') {
                        return normalizedPhoneSet(
                            all
                                .filter((opt) => String(opt.dataset.contactType || '') === 'client')
                                .map((opt) => opt.dataset.phone || '')
                        );
                    }
                    if (src === 'all_employees') {
                        return normalizedPhoneSet(
                            all
                                .filter((opt) => String(opt.dataset.contactType || '') === 'employee')
                                .map((opt) => opt.dataset.phone || '')
                        );
                    }
                    if (src === 'selected_contacts') {
                        return normalizedPhoneSet(
                            selectedVisibleContactOptions().map((opt) => opt.dataset.phone || '')
                        );
                    }

                    return new Set();
                };

                const updatePreview = () => {
                    if (!preview) {
                        return;
                    }
                    const src = source.value || 'manual';
                    if (src === 'manual') {
                        manualDraft = recipients.value;
                        const manualCount = parseManualCount();
                        preview.textContent = `${manualCount} phone number(s) selected.`;
                        return;
                    }
                    if (src === 'all_clients') {
                        const phones = autoDerivedPhoneSet(src);
                        preview.textContent = `${phones.size} client/lead phone number(s) will be used.`;
                        return;
                    }
                    if (src === 'all_employees') {
                        const phones = autoDerivedPhoneSet(src);
                        preview.textContent = `${phones.size} employee phone number(s) will be used.`;
                        return;
                    }

                    const selectedPhones = autoDerivedPhoneSet(src);
                    preview.textContent = `${selectedPhones.size} selected contact phone(s) will be used.`;
                };

                const sync = () => {
                    const src = source.value || 'manual';
                    const selectedContactsMode = src === 'selected_contacts';
                    const autoContactMode = src === 'all_clients' || src === 'all_employees' || selectedContactsMode;
                    const manualMode = src === 'manual';

                    contactsSelectionWrap?.classList.toggle('hidden', !selectedContactsMode);

                    recipients.required = manualMode;
                    recipients.readOnly = !manualMode;
                    recipients.classList.toggle('bg-slate-100', !manualMode);

                    if (autoContactMode) {
                        const phones = autoDerivedPhoneSet(src);
                        recipients.value = [...phones].join('\n');
                        recipients.placeholder = 'Recipients are auto-derived from loan contact records based on your selection above.';
                    } else {
                        recipients.value = manualDraft;
                        recipients.placeholder = '';
                    }

                    updatePreview();
                };

                source.addEventListener('change', sync);
                contactsSelect?.addEventListener('change', updatePreview);
                contactsSelect?.addEventListener('mousedown', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLOptionElement)) {
                        return;
                    }

                    // Toggle option on click without requiring Ctrl/Cmd.
                    event.preventDefault();
                    target.selected = !target.selected;
                    contactsSelect.focus();
                    updatePreview();
                    sync();
                });
                contactsSelectAllBtn?.addEventListener('click', () => {
                    if (!contactsSelect) {
                        return;
                    }
                    [...contactsSelect.options].forEach((opt) => {
                        opt.selected = true;
                    });
                    updatePreview();
                    sync();
                });
                contactsClearBtn?.addEventListener('click', () => {
                    if (!contactsSelect) {
                        return;
                    }
                    [...contactsSelect.options].forEach((opt) => {
                        opt.selected = false;
                    });
                    updatePreview();
                    sync();
                });
                templateSelect?.addEventListener('change', () => {
                    if (!messageInput || !templateSelect) {
                        return;
                    }

                    const selectedOption = templateSelect.options[templateSelect.selectedIndex];
                    const body = String(selectedOption?.dataset.templateBody || '');

                    if (body !== '') {
                        messageInput.value = body;
                    }
                });
                recipients.addEventListener('input', updatePreview);
                sync();
            })();
        </script>
    </x-loan.page>
</x-loan-layout>
