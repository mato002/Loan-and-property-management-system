<x-property-layout>
    <x-slot name="header">Bank sync</x-slot>

    <x-property.page
        title="Collection bank sync"
        subtitle="Choose the bank your agency collects rent through, then enter the API credentials from that bank. Supported Kenyan banks appear in the list below."
    >
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.bank'])

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="mb-4 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Selected bank</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $providerLabel }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</p>
                <p class="mt-1 text-sm font-semibold {{ $isConfigured ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $isConfigured ? 'Ready' : 'Not configured' }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Paybill / till</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $paybillNumber !== '' ? $paybillNumber : 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Auto sync</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                    @if ($hasAutoSync)
                        {{ $syncEnabled && $isActiveProvider ? 'Enabled' : ($isActiveProvider ? 'Paused' : 'Other bank active') }}
                    @else
                        Webhook only
                    @endif
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 w-full min-w-0">
            <form method="post" action="{{ route('property.settings.bank.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4 min-w-0">
                @csrf
                <input type="hidden" name="collection_bank_provider" value="{{ $provider }}" />

                <div>
                    <label class="block text-xs font-medium text-slate-500">Collection bank</label>
                    <select id="bank-provider-select" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2">
                        @foreach ($banks as $bank)
                            <option value="{{ $bank['id'] }}" @selected($provider === $bank['id'])>
                                {{ $bank['label'] }}@if ($bank['has_auto_sync']) (auto sync)@else (webhook)@endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">Pick the bank your tenants pay into. Only banks integrated in Passion are listed.</p>
                </div>

                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $providerLabel }} API credentials</h2>
                <p class="text-xs text-slate-500">Obtain these from your bank when enabling paybill / collection API access.</p>

                <div>
                    <label class="block text-xs font-medium text-slate-500">API base URL</label>
                    <input type="url" name="bank_base_url" value="{{ old('bank_base_url', $baseUrl) }}" placeholder="https://api.examplebank.co.ke" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                </div>

                @if ($authType === 'oauth')
                    <div>
                        <label class="block text-xs font-medium text-slate-500">API username</label>
                        <input type="text" name="bank_username" value="{{ old('bank_username', $username) }}" autocomplete="off" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">API password</label>
                        <input type="password" name="bank_password" autocomplete="new-password" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="{{ $hasPassword ? 'Leave blank to keep saved value' : 'Not set' }}" />
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-slate-500">API key</label>
                    <input type="text" name="bank_api_key" value="{{ old('bank_api_key', $apiKey) }}" autocomplete="off" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">API secret</label>
                    <input type="password" name="bank_api_secret" autocomplete="new-password" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="{{ $hasApiSecret ? 'Leave blank to keep saved value' : 'Not set' }}" />
                </div>

                @if ($authType === 'api_key')
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Merchant / till code</label>
                        <input type="text" name="bank_merchant_code" value="{{ old('bank_merchant_code', $merchantCode) }}" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-slate-500">Paybill / till number (shown to staff)</label>
                    <input type="text" name="bank_paybill_number" value="{{ old('bank_paybill_number', $paybillNumber) }}" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="e.g. 247247" />
                    <p class="mt-1 text-[11px] text-slate-500">Tenants should enter their <strong>TNT account</strong> (e.g. TNT000416) as the account number when paying.</p>
                </div>

                @if ($hasAutoSync)
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="bank_sync_enabled" value="0" />
                        <input type="checkbox" name="bank_sync_enabled" value="1" id="bank_sync_enabled" @checked(old('bank_sync_enabled', $syncEnabled ? '1' : '0') === '1' || old('bank_sync_enabled') === true) class="rounded border-slate-300" />
                        <label for="bank_sync_enabled" class="text-sm text-slate-700 dark:text-slate-200">Enable automatic paybill sync (every {{ $syncIntervalMinutes }} min when scheduler is running)</label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Sync interval (minutes)</label>
                        <input type="number" min="1" max="60" name="bank_sync_interval_minutes" value="{{ old('bank_sync_interval_minutes', $syncIntervalMinutes) }}" class="mt-1 w-32 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    </div>
                @else
                    <p class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs text-slate-600">Automatic transaction pull is not enabled for {{ $providerLabel }} yet. Save credentials and register the webhook URL — payments will flow in when the bank pushes them.</p>
                @endif

                @if ($supportsWebhook)
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Webhook secret (optional)</label>
                        <input type="password" name="bank_webhook_secret" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="{{ $hasWebhookSecret ? 'Leave blank to keep saved value' : 'Optional' }}" />
                    </div>
                @endif

                @if ($authType === 'oauth')
                    <details class="rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                        <summary class="cursor-pointer text-xs font-medium text-slate-600">Advanced API paths</summary>
                        <div class="mt-3 space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Auth endpoint</label>
                                <input type="text" name="bank_auth_endpoint" value="{{ old('bank_auth_endpoint', $authEndpoint) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Transactions endpoint</label>
                                <input type="text" name="bank_transactions_endpoint" value="{{ old('bank_transactions_endpoint', $transactionsEndpoint) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Balance endpoint</label>
                                <input type="text" name="bank_balance_endpoint" value="{{ old('bank_balance_endpoint', $balanceEndpoint) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                            </div>
                        </div>
                    </details>
                @endif

                <div>
                    <label class="block text-xs font-medium text-slate-500">Internal notes</label>
                    <textarea name="bank_notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2">{{ old('bank_notes', $notes) }}</textarea>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save bank settings</button>
                    @if ($hasAutoSync)
                        <button type="submit" name="test_connection" value="1" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Test connection</button>
                    @endif
                </div>
            </form>

            <div class="space-y-4 min-w-0">
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-3">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Integrated banks</h2>
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        @foreach ($banks as $bank)
                            <li class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 {{ $provider === $bank['id'] ? 'bg-blue-50 border-blue-100' : '' }}">
                                <span>{{ $bank['label'] }}</span>
                                <span class="text-[11px] font-medium uppercase tracking-wide {{ $bank['has_auto_sync'] ? 'text-emerald-700' : 'text-slate-500' }}">
                                    {{ $bank['has_auto_sync'] ? 'Auto sync' : 'Webhook' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-slate-500">Need another bank? Contact support — new integrations are added to this list when API access is available.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-3">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">How matching works</h2>
                    <ul class="list-disc pl-5 text-sm text-slate-600 dark:text-slate-300 space-y-1">
                        <li>Bank paybill transactions arrive via auto sync or webhook.</li>
                        <li>Each payment is matched to a tenant by <strong>TNT account</strong>, phone, or invoice number.</li>
                        <li>Matched payments allocate to open invoices automatically (oldest first).</li>
                        <li>Unmatched payments appear under <a href="{{ route('property.equity.unmatched', [], false) }}" class="text-blue-600 hover:underline">Collections → Unmatched</a>.</li>
                    </ul>
                </div>

                @if ($supportsWebhook)
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-3">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Bank webhook</h2>
                        <p class="text-xs text-slate-500">Register this URL with {{ $providerLabel }} for real-time payment push:</p>
                        <div class="flex gap-2">
                            <input id="bank-webhook-url" readonly value="{{ $webhookUrl }}" class="flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-xs font-mono px-3 py-2" />
                            <button type="button" data-copy-target="#bank-webhook-url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Copy</button>
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/70 dark:border-indigo-900/40 dark:bg-indigo-950/20 p-4 sm:p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">Operations</h2>
                    <p class="mt-1 text-sm text-indigo-800 dark:text-indigo-300">After saving, open sync status to run a manual pull or review matched/unmatched queues.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('property.equity.sync_status', [], false) }}" class="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Bank sync status</a>
                        <a href="{{ route('property.settings.payments', [], false) }}" class="inline-flex rounded-xl border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-800 hover:bg-indigo-100">STK / M-Pesa config</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <a href="{{ route('property.settings.index') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">← Back to settings</a>
        </div>
    </x-property.page>

    @push('scripts')
    <script>
        document.getElementById('bank-provider-select')?.addEventListener('change', function () {
            const url = new URL(@json(route('property.settings.bank')), window.location.origin);
            url.searchParams.set('provider', this.value);
            window.location.href = url.toString();
        });
        document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const sel = btn.getAttribute('data-copy-target');
                const el = sel ? document.querySelector(sel) : null;
                if (!el) return;
                const text = el.value || el.textContent || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text.trim());
                } else if (el.select) {
                    el.select();
                    document.execCommand('copy');
                }
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            });
        });
    </script>
    @endpush
</x-property-layout>
