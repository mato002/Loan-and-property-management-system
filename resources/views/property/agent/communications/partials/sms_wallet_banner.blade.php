@php
    $wallet = (array) ($smsWallet ?? []);
    $balanceUrl = route('property.communications.sms_balance', absolute: false);
@endphp

@if ($wallet !== [])
    <div
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
        data-sms-wallet-banner
        x-data="{
            wallet: @js($wallet),
            refreshing: false,
            balanceUrl: @js($balanceUrl),
            async refreshWallet() {
                if (this.refreshing) return;
                this.refreshing = true;
                try {
                    const response = await fetch(this.balanceUrl, { headers: { Accept: 'application/json' } });
                    const json = await response.json();
                    if (json.ok && json.wallet) {
                        this.wallet = json.wallet;
                    }
                } catch (e) {}
                this.refreshing = false;
            }
        }"
    >
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">SMS Balance</p>
                <button
                    type="button"
                    class="rounded-md border border-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/50"
                    :disabled="refreshing"
                    @click="refreshWallet()"
                >
                    <span x-show="!refreshing">Refresh</span>
                    <span x-show="refreshing">…</span>
                </button>
            </div>
            <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                <span x-text="Number(wallet.balance || 0).toFixed(2)"></span>
                <span class="text-sm font-medium text-slate-500" x-text="wallet.currency || 'KES'"></span>
            </p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300" x-show="wallet.provider_units">
                <span x-text="Number(wallet.provider_units || 0).toFixed(0)"></span> SMS units (provider)
            </p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300" x-show="!wallet.provider_units">
                About <span x-text="wallet.max_recipients || 0"></span> SMS available
            </p>
            <p class="mt-2 text-[10px] text-slate-400 dark:text-slate-500">
                Source: <span x-text="wallet.balance_source || 'SMS wallet'"></span>
            </p>
            <p class="mt-1 text-[10px] text-amber-700 dark:text-amber-300" x-show="wallet.balance_pending_debit">
                Includes <span x-text="Number(wallet.balance_pending_debit || 0).toFixed(2)"></span>
                <span x-text="wallet.currency || 'KES'"></span> from recent sends (provider API may lag).
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Cost per SMS</p>
            <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                <span x-text="Number(wallet.cost_per_sms || 0).toFixed(2)"></span>
                <span class="text-sm font-medium text-slate-500" x-text="wallet.currency || 'KES'"></span>
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="wallet.cost_source ? (wallet.cost_source + ' · per message') : 'Per outbound message'"></p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Max recipients now</p>
            <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-white" x-text="wallet.max_recipients || 0"></p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">At current balance</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Provider status</p>
            <div class="mt-2 flex items-center gap-2">
                <span
                    class="inline-flex h-2 w-2 rounded-full"
                    :class="wallet.provider_ok ? 'bg-emerald-500' : 'bg-rose-500'"
                ></span>
                <p class="text-sm font-semibold text-slate-900 dark:text-white" x-text="wallet.provider_ok ? 'Connected' : 'Not connected'"></p>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-show="!wallet.provider_ok && wallet.provider_error" x-text="wallet.provider_error"></p>
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300" x-show="wallet.provider_ok">Pradytec API reachable</p>
        </div>
    </div>
@endif
