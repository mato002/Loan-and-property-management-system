(() => {
    const modal = document.querySelector('[data-statement-split-modal]');
    const form = modal?.querySelector('[data-statement-split]');
    const rows = modal?.querySelector('[data-split-rows]');
    if (!modal || !form || !rows) return;

    const totalLabel = modal.querySelector('[data-split-total-label]');
    const referenceLabel = modal.querySelector('[data-split-reference-label]');
    const status = modal.querySelector('[data-split-total-status]');
    const submit = modal.querySelector('[data-submit-statement-split]');
    let transactionCents = 0;
    let nextRow = 0;

    const money = (cents) => `KES ${(cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
    };
    const validateTotals = () => {
        const recipientRows = [...rows.querySelectorAll('[data-statement-split-row]')];
        const selected = recipientRows.map((row) => ({
            tenantId: row.querySelector('[data-split-tenant-id]')?.value || '',
            cents: Math.round((Number(row.querySelector('[data-split-amount]')?.value) || 0) * 100),
        }));
        const assignedCents = selected.reduce((sum, item) => sum + item.cents, 0);
        const allTenantsSelected = selected.every((item) => item.tenantId !== '');
        const distinctTenants = new Set(selected.map((item) => item.tenantId)).size === selected.length;
        const allPositive = selected.every((item) => item.cents > 0);
        const matches = assignedCents === transactionCents;
        const remaining = transactionCents - assignedCents;

        status.textContent = matches
            ? `Fully allocated: ${money(assignedCents)}`
            : remaining > 0
                ? `${money(remaining)} remaining to allocate`
                : `${money(Math.abs(remaining))} over the receipt amount`;
        status.classList.toggle('text-emerald-700', matches);
        status.classList.toggle('text-amber-700', !matches);
        submit.disabled = recipientRows.length < 2 || !allTenantsSelected || !distinctTenants || !allPositive || !matches;
    };

    const addRecipient = () => {
        const index = nextRow++;
        const row = document.createElement('div');
        row.setAttribute('data-statement-split-row', '1');
        row.className = 'grid grid-cols-1 items-start gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto]';
        row.innerHTML = `
            <div class="relative">
                <input type="hidden" name="allocations[${index}][tenant_id]" data-split-tenant-id>
                <input type="text" data-tenant-query data-search-url="${form.dataset.searchUrl}" autocomplete="off" placeholder="Tenant, account, or phone" required class="h-10 w-full rounded-lg border border-slate-300 px-3 pr-8 text-sm focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                <span data-search-spinner class="pointer-events-none absolute right-2 top-3 hidden text-slate-400" aria-hidden="true">…</span>
            </div>
            <label class="text-xs font-semibold text-slate-600">Amount
                <input type="number" name="allocations[${index}][amount]" data-split-amount min="0.01" step="0.01" inputmode="decimal" required class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums">
            </label>
            <button type="button" data-remove-split-recipient class="mt-1 rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove</button>
        `;
        rows.appendChild(row);
        validateTotals();
    };

    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-open-statement-split]');
        if (openButton) {
            transactionCents = Math.round(Number(openButton.dataset.splitAmount || 0) * 100);
            form.action = openButton.dataset.splitUrl;
            referenceLabel.textContent = openButton.dataset.splitReference || 'Bank transaction';
            totalLabel.textContent = money(transactionCents);
            rows.replaceChildren();
            nextRow = 0;
            addRecipient();
            addRecipient();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            rows.querySelector('[data-tenant-query]')?.focus();
            return;
        }

        if (event.target.closest('[data-close-statement-split]') || event.target === modal) {
            close();
            return;
        }
        if (event.target.closest('[data-add-split-recipient]')) {
            addRecipient();
            return;
        }
        const remove = event.target.closest('[data-remove-split-recipient]');
        if (remove) {
            if (rows.querySelectorAll('[data-statement-split-row]').length > 2) {
                remove.closest('[data-statement-split-row]')?.remove();
                validateTotals();
            }
        }
    });

    document.addEventListener('input', (event) => {
        if (event.target.closest('[data-statement-split]')) validateTotals();
    });
    document.addEventListener('tenant:selected', (event) => {
        if (event.target.closest('[data-statement-split]')) validateTotals();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') close();
    });
    form.addEventListener('submit', (event) => {
        validateTotals();
        if (submit.disabled) event.preventDefault();
    });
})();
