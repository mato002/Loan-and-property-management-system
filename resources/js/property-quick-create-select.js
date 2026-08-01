/**
 * Searchable combobox + optional AJAX create for <x-property.quick-create-select>.
 */

function parseConfig(raw) {
    if (!raw) {
        return {};
    }
    if (typeof raw === 'object') {
        return raw;
    }
    try {
        return JSON.parse(raw);
    } catch {
        return {};
    }
}

function propertyQuickCreateSelect(config) {
    const cfg = parseConfig(config);

    return {
        open: false,
        creating: false,
        pickerOpen: false,
        query: '',
        selectedValue: '',
        options: Array.isArray(cfg.options) ? cfg.options : [],
        useSearch: Boolean(cfg.useSearch),
        placeholder: cfg.placeholder || 'Select…',
        selectId: cfg.selectId || '',
        createMode: cfg.createMode || 'none',
        createEndpoint: cfg.createEndpoint || '',
        createFields: Array.isArray(cfg.createFields) ? cfg.createFields : [],

        closeCreateModal() {
            this.open = false;
        },

        openCreateModal() {
            this.closePicker();
            this.open = true;
        },

        init() {
            const selected = this.options.find((o) => o.selected);
            this.selectedValue = selected ? String(selected.value) : '';
            const native = this.$refs.nativeSelect;
            if (native instanceof HTMLSelectElement) {
                if (native.value !== this.selectedValue) {
                    native.value = this.selectedValue;
                }
                native.addEventListener('change', () => {
                    const next = native.value;
                    if (String(this.selectedValue) !== String(next)) {
                        this.selectedValue = next;
                    }
                });
            }
        },

        get filteredOptions() {
            const q = this.query.trim().toLowerCase();
            if (!q) {
                return this.options;
            }

            return this.options.filter((opt) => {
                const hay = String(opt.search ?? opt.label ?? '').toLowerCase();

                return hay.includes(q);
            });
        },

        get selectedLabel() {
            if (!this.selectedValue) {
                return this.placeholder;
            }
            const match = this.options.find((o) => String(o.value) === String(this.selectedValue));

            return match ? String(match.label) : this.placeholder;
        },

        togglePicker() {
            this.pickerOpen = !this.pickerOpen;
            if (this.pickerOpen) {
                this.query = '';
                this.$nextTick(() => this.$refs.searchInput?.focus?.());
            }
        },

        closePicker() {
            this.pickerOpen = false;
            this.query = '';
        },

        pickOption(opt) {
            this.selectedValue = String(opt.value);
            this.syncHiddenSelect();
            this.closePicker();
        },

        syncHiddenSelect() {
            const sel = this.$refs.nativeSelect;
            if (!(sel instanceof HTMLSelectElement)) {
                return;
            }
            const next = this.selectedValue;
            if (sel.value === next) {
                return;
            }
            sel.value = next;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        },

        appendCreatedItem(item) {
            if (!item || item.id === undefined || item.id === null) {
                return;
            }
            const label = item.label
                ? String(item.label)
                : (item.name ? String(item.name) : String(item.id));
            const search = item.search
                ? String(item.search)
                : label.toLowerCase();
            this.options.push({
                value: item.id,
                label,
                search,
            });
            this.selectedValue = String(item.id);
            this.syncHiddenSelect();
        },

        async submit() {
            if (this.createMode !== 'ajax' || !this.createEndpoint) {
                return;
            }

            const payload = {};
            for (const f of this.createFields) {
                const el = document.getElementById(`${this.selectId}-f-${f.name}`);
                if (el instanceof HTMLTextAreaElement || el instanceof HTMLInputElement || el instanceof HTMLSelectElement) {
                    payload[f.name] = el.value !== undefined ? String(el.value).trim() : '';
                } else {
                    payload[f.name] = '';
                }
                if (f.required && !payload[f.name]) {
                    const msg = `${f.label || f.name} is required.`;
                    Swal.fire({ icon: 'warning', title: 'Missing field', text: msg });

                    return;
                }
            }

            this.creating = true;
            try {
                const token = document.querySelector('input[name=_token]')?.value || '';
                const res = await fetch(this.createEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) {
                    const msg = data?.message || data?.error || 'Could not create record.';
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });

                    return;
                }

                const item = data.item || data.user || data.record;
                this.appendCreatedItem(item);

                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Created',
                        text: data.message || 'Created.',
                        timer: 1500,
                        showConfirmButton: false,
                    });
                }
                this.open = false;
            } catch {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network/server error while creating.' });
            } finally {
                this.creating = false;
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('propertyQuickCreateSelect', (config) => propertyQuickCreateSelect(config));
});

window.propertyQuickCreateSelect = propertyQuickCreateSelect;

export { propertyQuickCreateSelect };
