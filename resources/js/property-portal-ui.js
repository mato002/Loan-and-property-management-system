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
