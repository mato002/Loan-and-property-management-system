@php
    $typeOptions = $typeOptions ?? [];
    $parentOptions = $parentOptions ?? [];
    $usageOptions = $usageOptions ?? [];
@endphp

<div
    id="coa-page-modals"
    x-data="{
        showCreate: false,
        showDisable: false,
        disableAction: '',
        disableDetails: ''
    }"
>
    <x-property.modal
        show="showCreate"
        close="showCreate = false"
        name="coa-create"
        title="Add account"
        max-width="2xl"
    >
        <form method="post" action="{{ route('property.accounting.gl.chart_accounts.store') }}" class="grid gap-3 sm:grid-cols-2">
            @csrf
            <div><label class="text-xs">Account code</label><input required name="code" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
            <div><label class="text-xs">Account name</label><input required name="name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
            <div><label class="text-xs">Account type</label><select id="coa-type" name="type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach ($typeOptions as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach</select></div>
            <div><label class="text-xs">Parent account</label><select name="parent_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">—</option>@foreach ($parentOptions as $p)<option value="{{ $p['id'] }}">{{ $p['label'] }}</option>@endforeach</select></div>
            <div><label class="text-xs">Normal balance</label><select name="normal_balance" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="debit">Debit</option><option value="credit">Credit</option></select></div>
            <div><label class="text-xs">Default usage mapping</label><select name="default_usage" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">—</option>@foreach ($usageOptions as $u)<option value="{{ $u }}">{{ ucfirst($u) }}</option>@endforeach</select></div>
            <div><label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" name="is_control_account" value="1"> Is control account</label></div>
            <div><label class="text-xs">Status</label><select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="active">Active</option><option value="disabled">Disabled</option></select></div>
            <div class="sm:col-span-2 text-xs text-slate-500">Suggested code ranges: 1000-1999 Assets, 2000-2999 Liabilities, 3000-3999 Equity, 4000-4999 Income, 5000-5999 Expenses.</div>
            <div class="sm:col-span-2">
                <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" type="submit">Create account</button>
            </div>
        </form>
    </x-property.modal>

    <x-property.modal
        show="showDisable"
        close="showDisable = false"
        name="coa-disable"
        title="Disable account"
        max-width="lg"
    >
        <p class="text-sm text-slate-600 dark:text-slate-300" x-text="disableDetails"></p>
        <form :action="disableAction" method="post" class="mt-4">
            @csrf
            <input type="hidden" name="confirm" value="yes" />
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">Disable account</button>
                <button type="button" @click="showDisable = false" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Cancel</button>
            </div>
        </form>
    </x-property.modal>
</div>

<script>
    (function () {
        if (window.__coaModalsBound) {
            return;
        }
        window.__coaModalsBound = true;

        const rootId = 'coa-page-modals';
        const disableUrlTemplate = @json(route('property.accounting.gl.chart_accounts.disable', ['account' => '__ID__']));

        const data = () => window.Alpine?.$data(document.getElementById(rootId));

        window.openCoaCreateModal = function () {
            const state = data();
            if (state) {
                state.showCreate = true;
            }
        };

        window.openDisableModal = function (id, code, name, balance, txCount, mappingUsed, isProtected) {
            if (isProtected) {
                return;
            }
            const state = data();
            if (!state) {
                return;
            }
            state.disableAction = disableUrlTemplate.replace('__ID__', String(id));
            state.disableDetails = `${code} ${name} | Balance: ${balance} | Transactions: ${txCount} | Used in mappings: ${mappingUsed ? 'Yes' : 'No'}.`;
            state.showDisable = true;
        };

        window.closeDisableModal = function () {
            const state = data();
            if (state) {
                state.showDisable = false;
            }
        };

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (target.closest('#coa-open-create')) {
                window.openCoaCreateModal();
            }
        });
    })();
</script>
