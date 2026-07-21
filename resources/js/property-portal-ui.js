/**
 * Client-side filter: `data-table-filter="parent"` scopes to `.property-ws-wrap`, or set to a table wrapper element id.
 * Multiple controls with the same mode (e.g. two `parent` filters) are combined with AND.
 */
function applyPropertyTableFilters(el) {
    const mode = el.getAttribute('data-table-filter');
    if (!mode) {
        return;
    }

    if (mode === 'parent') {
        const wrap = el.closest('.property-ws-wrap');
        if (!wrap) {
            return;
        }
        const controls = wrap.querySelectorAll('[data-table-filter="parent"]');
        const rows = [
            ...wrap.querySelectorAll('tbody tr[data-filter-text]'),
            ...wrap.querySelectorAll('[data-mobile-record-list] article[data-filter-text]'),
        ];
        rows.forEach((row) => {
            const hay = (row.getAttribute('data-filter-text') || '').toLowerCase();
            let visible = true;
            controls.forEach((c) => {
                const q = (c.value || '').toLowerCase().trim();
                if (q === '') {
                    return;
                }
                if (!hay.includes(q)) {
                    visible = false;
                }
            });
            row.classList.toggle('hidden', !visible);
        });
        return;
    }

    const scope = document.getElementById(mode);
    if (!scope) {
        return;
    }
    const controls = document.querySelectorAll(`[data-table-filter="${CSS.escape(mode)}"]`);
    const rows = scope.querySelectorAll('tbody tr[data-filter-text]');
    rows.forEach((row) => {
        const hay = (row.getAttribute('data-filter-text') || '').toLowerCase();
        let visible = true;
        controls.forEach((c) => {
            const q = (c.value || '').toLowerCase().trim();
            if (q === '') {
                return;
            }
            if (!hay.includes(q)) {
                visible = false;
            }
        });
        row.classList.toggle('hidden', !visible);
    });
}

document.addEventListener('input', (e) => {
    const el = e.target.closest('input[data-table-filter], select[data-table-filter]');
    if (!el) {
        return;
    }
    applyPropertyTableFilters(el);
});

document.addEventListener('change', (e) => {
    const el = e.target.closest('select[data-table-filter], input[type="month"][data-table-filter]');
    if (!el) {
        return;
    }
    applyPropertyTableFilters(el);
});

function initPropertyTableFilters(root = document) {
    const controls = root.querySelectorAll?.('input[data-table-filter], select[data-table-filter]') ?? [];
    controls.forEach((el) => applyPropertyTableFilters(el));
}

document.addEventListener('DOMContentLoaded', () => initPropertyTableFilters(document));
document.addEventListener('turbo:load', () => initPropertyTableFilters(document));
document.addEventListener('turbo:frame-load', (e) => initPropertyTableFilters(e.target));

function initKenyaAddressAutocomplete(root = document) {
    const inputs = root.querySelectorAll?.('input[data-ke-address-autocomplete]') ?? [];
    inputs.forEach((input) => {
        if (input.dataset.keAddrInit === '1') {
            return;
        }
        input.dataset.keAddrInit = '1';

        const listId = input.getAttribute('list');
        const listEl = listId ? document.getElementById(listId) : null;
        if (!listEl) {
            return;
        }

        const findCityValue = () => {
            const form = input.closest('form');
            const cityEl = form?.querySelector?.('[name="city"]') ?? null;
            const city = (cityEl?.value || '').trim();
            return city;
        };

        let timer = null;
        let lastQ = '';

        const fetchSuggestions = async () => {
            const q = (input.value || '').trim();
            if (q.length < 3 || q === lastQ) {
                return;
            }
            lastQ = q;
            try {
                const city = findCityValue();
                const endpoint = (input.dataset.keAddressEndpoint || '').trim() || '/property/geo/kenya-addresses';
                const url = `${endpoint}?q=${encodeURIComponent(q)}&city=${encodeURIComponent(city)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                const items = Array.isArray(data?.items) ? data.items : [];
                listEl.innerHTML = '';
                items.slice(0, 6).forEach((it) => {
                    if (!it?.label) {
                        return;
                    }
                    const opt = document.createElement('option');
                    opt.value = it.label;
                    listEl.appendChild(opt);
                });
            } catch {
                // ignore
            }
        };

        input.addEventListener('input', () => {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(fetchSuggestions, 250);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => initKenyaAddressAutocomplete(document));
document.addEventListener('turbo:load', () => initKenyaAddressAutocomplete(document));
document.addEventListener('turbo:frame-load', (e) => initKenyaAddressAutocomplete(e.target));

// Global helper: robustly set a form field value and trigger change/input for custom components
// Exposes window.pmSetFieldValue(nameOrEl, value, [rootFormOrDocument])
// - nameOrEl: a string field name or a direct element reference
// - value: the value to set (coerced to string)
// - root: optional scope element to resolve by name (defaults to document)
;(function () {
    if (window.pmSetFieldValue) {
        return;
    }
    function resolveElement(nameOrEl, root) {
        if (nameOrEl && typeof nameOrEl === 'object' && nameOrEl.nodeType === 1) {
            return nameOrEl;
        }
        const scope = root && root.querySelector ? root : document;
        // Prefer [name="..."] to support custom select wrappers/hidden inputs
        const byName = scope.querySelector?.(`[name="${nameOrEl}"]`) || null;
        if (byName) return byName;
        // Fallback: id
        const byId = document.getElementById(String(nameOrEl));
        if (byId) return byId;
        return null;
    }
    function dispatchAll(el) {
        try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
        try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
    }
    window.pmSetFieldValue = function pmSetFieldValue(nameOrEl, value, root) {
        const el = resolveElement(nameOrEl, root);
        if (!el) return false;
        const v = String(value);
        if (el.tagName && el.tagName.toLowerCase() === 'select') {
            const before = el.value;
            el.value = v;
            if (el.value !== v) {
                // Option not present yet (virtualized or async); append a temporary option
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v;
                el.appendChild(opt);
                el.value = v;
            }
            if (el.value !== before) {
                dispatchAll(el);
            }
            return true;
        }
        // Try inner input for custom components
        const inner = el.querySelector?.('input, select') || null;
        if (inner) {
            const before = inner.value;
            inner.value = v;
            if (inner.value !== before) {
                dispatchAll(inner);
            }
            return true;
        }
        const before = el.value;
        el.value = v;
        if (el.value !== before) {
            dispatchAll(el);
        }
        return true;
    };
})();

// Lightweight SweetAlert binding for forms with data-swal-confirm.
;(function () {
    function onSubmit(e) {
        const form = e.target.closest('form[data-swal-confirm]');
        if (!form) return;
        const msg = form.getAttribute('data-swal-confirm') || 'Are you sure?';
        if (window.Swal && typeof window.Swal.fire === 'function') {
            e.preventDefault();
            window.Swal.fire({
                icon: 'warning',
                title: 'Please confirm',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Yes, continue',
                cancelButtonText: 'Cancel',
            }).then((res) => {
                if (res.isConfirmed) {
                    form.submit();
                }
            });
        }
    }
    document.addEventListener('submit', onSubmit);
})();

function initPropertyBareTableScroll(root = document) {
    const scope = root.querySelector?.('#property-main') ?? root;
    if (!scope || typeof scope.querySelectorAll !== 'function') {
        return;
    }

    scope.querySelectorAll('table').forEach((table) => {
        if (table.closest('.property-table-scroll')) {
            return;
        }
        if (table.closest('[data-skip-table-scroll]')) {
            return;
        }
        if (table.closest('[data-mobile-record-list]')) {
            return;
        }

        const parent = table.parentElement;
        if (!parent) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'property-table-scroll -mx-3 px-3 sm:-mx-4 sm:px-4 md:mx-0 md:px-0 overflow-x-auto w-full min-w-0';
        const inner = document.createElement('div');
        inner.className = 'w-full';
        inner.style.minWidth = '640px';

        parent.insertBefore(wrapper, table);
        inner.appendChild(table);
        wrapper.appendChild(inner);
    });
}

document.addEventListener('DOMContentLoaded', () => initPropertyBareTableScroll(document));
document.addEventListener('turbo:load', () => initPropertyBareTableScroll(document));
document.addEventListener('turbo:frame-load', (e) => initPropertyBareTableScroll(e.target));
