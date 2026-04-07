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
                                    <option value="all_tenants" @selected(old('recipient_source') === 'all_tenants')>All active tenants</option>
                                    <option value="property_tenants" @selected(old('recipient_source') === 'property_tenants')>Tenants of a specific property</option>
                                </select>
                            </div>
                            <div id="property-wrap" class="hidden">
                                <label for="property_id" class="block text-sm font-medium text-slate-700">Property</label>
                                <select id="property_id" name="property_id"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">
                                    <option value="">— Select property —</option>
                                    @foreach (($propertyOptions ?? collect()) as $p)
                                        <option value="{{ $p->id }}" @selected((string) old('property_id') === (string) $p->id)>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                @error('property_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div id="tenant-selection-wrap" class="hidden">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="tenant_selection_mode" class="block text-sm font-medium text-slate-700">Tenant selection</label>
                                    <select id="tenant_selection_mode" name="tenant_selection_mode"
                                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">
                                        <option value="all" @selected(old('tenant_selection_mode', 'all') === 'all')>All tenants in selected property</option>
                                        <option value="selected" @selected(old('tenant_selection_mode') === 'selected')>Select specific tenants</option>
                                    </select>
                                </div>
                            </div>
                            <div id="tenant-list-wrap" class="hidden mt-3">
                                <label for="tenant_ids" class="block text-sm font-medium text-slate-700">Choose tenants</label>
                                <select id="tenant_ids" name="tenant_ids[]" multiple
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f] min-h-[160px]">
                                    @foreach (($tenantOptions ?? collect()) as $tenant)
                                        <option
                                            value="{{ $tenant['id'] }}"
                                            data-property-ids="{{ implode(',', $tenant['property_ids'] ?? []) }}"
                                            @selected(collect(old('tenant_ids', []))->contains((string) $tenant['id']) || collect(old('tenant_ids', []))->contains((int) $tenant['id']))
                                        >
                                            {{ $tenant['name'] }} ({{ $tenant['phone'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-slate-500 mt-1">Hold Ctrl/Cmd to select multiple tenants.</p>
                                @error('tenant_ids')
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
                                        <option value="{{ $t->id }}" @selected((string) old('sms_template_id', $prefillTemplateId) === (string) $t->id)>{{ $t->name }}</option>
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
                const propertyWrap = document.getElementById('property-wrap');
                const propertySelect = document.getElementById('property_id');
                const tenantSelectionWrap = document.getElementById('tenant-selection-wrap');
                const tenantMode = document.getElementById('tenant_selection_mode');
                const tenantListWrap = document.getElementById('tenant-list-wrap');
                const tenantSelect = document.getElementById('tenant_ids');
                const recipients = document.getElementById('recipients');
                const preview = document.getElementById('recipient-preview');

                if (!source || !recipients) {
                    return;
                }

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

                const visibleTenantOptions = () => {
                    if (!tenantSelect) {
                        return [];
                    }
                    return [...tenantSelect.options].filter((opt) => !opt.hidden);
                };

                const selectedVisibleTenantOptions = () => {
                    return visibleTenantOptions().filter((opt) => opt.selected);
                };

                const updatePreview = () => {
                    if (!preview) {
                        return;
                    }
                    const src = source.value || 'manual';
                    if (src === 'manual') {
                        const manualCount = parseManualCount();
                        preview.textContent = `${manualCount} phone number(s) selected.`;
                        return;
                    }
                    if (src === 'all_tenants') {
                        const allCount = [...(tenantSelect?.options || [])].length;
                        preview.textContent = `${allCount} active tenant phone(s) will be used.`;
                        return;
                    }

                    const mode = tenantMode?.value || 'all';
                    if (mode === 'selected') {
                        const selectedCount = selectedVisibleTenantOptions().length;
                        preview.textContent = `${selectedCount} selected tenant phone(s) will be used.`;
                        return;
                    }
                    const propertyCount = visibleTenantOptions().length;
                    preview.textContent = `${propertyCount} tenant phone(s) in selected property will be used.`;
                };

                const filterTenantOptions = () => {
                    if (!tenantSelect) {
                        return;
                    }
                    const propertyId = String(propertySelect?.value || '');
                    [...tenantSelect.options].forEach((opt) => {
                        const propIds = String(opt.dataset.propertyIds || '').split(',').filter(Boolean);
                        const visible = !propertyId || propIds.includes(propertyId);
                        opt.hidden = !visible;
                        if (!visible) {
                            opt.selected = false;
                        }
                    });
                    updatePreview();
                };

                const sync = () => {
                    const src = source.value || 'manual';
                    const propertyMode = src === 'property_tenants';
                    const allTenantMode = src === 'all_tenants';
                    const manualMode = src === 'manual';

                    propertyWrap?.classList.toggle('hidden', !propertyMode);
                    tenantSelectionWrap?.classList.toggle('hidden', !propertyMode);
                    const showTenantList = propertyMode && (tenantMode?.value === 'selected');
                    tenantListWrap?.classList.toggle('hidden', !showTenantList);

                    recipients.required = manualMode;
                    recipients.disabled = !manualMode;
                    recipients.classList.toggle('bg-slate-100', !manualMode);
                    recipients.classList.toggle('cursor-not-allowed', !manualMode);

                    if (allTenantMode || propertyMode) {
                        recipients.placeholder = 'Recipients are auto-derived from tenant records based on your selection above.';
                    } else {
                        recipients.placeholder = '';
                    }

                    filterTenantOptions();
                    updatePreview();
                };

                source.addEventListener('change', sync);
                tenantMode?.addEventListener('change', sync);
                propertySelect?.addEventListener('change', sync);
                tenantSelect?.addEventListener('change', updatePreview);
                recipients.addEventListener('input', updatePreview);
                sync();
            })();
        </script>
    </x-loan.page>
</x-loan-layout>
