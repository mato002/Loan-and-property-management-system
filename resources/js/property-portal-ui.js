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
        const rows = wrap.querySelectorAll('tbody tr[data-filter-text]');
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
