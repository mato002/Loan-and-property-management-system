<x-property-layout>
    <x-slot name="header">Payment configs</x-slot>
    @php $isSuperAdmin = (bool) (auth()->user()->is_super_admin ?? false); @endphp

    <x-property.page
        title="Payment configs"
        subtitle="STK collection, B2C payouts, trust account, and automatic tenant receipts. Secrets are stored in portal settings (plain text in this build)."
    >
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.payments'])

        <div class="mb-4 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Shortcode</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $shortcode !== '' ? $shortcode : 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Consumer secret</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $hasConsumerSecret ? 'Configured' : 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Passkey</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $hasPasskey ? 'Configured' : 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Trust account</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ ($trustAccountLabel ?? '') !== '' ? $trustAccountLabel : 'Not set' }}</p>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2 w-full min-w-0">
            <form method="post" action="{{ route('property.settings.payments.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4 min-w-0">
                @csrf
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">M-Pesa STK Push (collection)</h2>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Shortcode / paybill / till</label>
                    <input type="text" name="mpesa_shortcode" value="{{ old('mpesa_shortcode', $shortcode) }}" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    @error('mpesa_shortcode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Consumer key</label>
                    <input type="text" name="mpesa_consumer_key" value="{{ old('mpesa_consumer_key', $consumerKey) }}" autocomplete="off" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    @error('mpesa_consumer_key')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Consumer secret</label>
                    <input type="password" name="mpesa_consumer_secret" value="{{ old('mpesa_consumer_secret') }}" autocomplete="off" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="{{ $hasConsumerSecret ? 'Leave blank to keep saved value' : 'Not set' }}" />
                    @error('mpesa_consumer_secret')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Passkey</label>
                    <input type="password" name="mpesa_passkey" value="{{ old('mpesa_passkey') }}" autocomplete="off" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="{{ $hasPasskey ? 'Leave blank to keep saved value' : 'Not set' }}" />
                    @error('mpesa_passkey')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Callback URL</label>
                    <input type="url" name="mpesa_callback_url" value="{{ old('mpesa_callback_url', $callbackUrl) }}" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    @error('mpesa_callback_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Internal notes</label>
                    <textarea name="payments_notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2">{{ old('payments_notes', $notes) }}</textarea>
                    @error('payments_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save payment settings</button>
            </form>

            <div class="space-y-4 min-w-0">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4 min-w-0">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Bank settlement</h2>
                <form method="post" action="{{ route('property.settings.payments.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="save_trust_account" value="1" />
                    <label class="block text-xs font-medium text-slate-500">Trust account label</label>
                    <input type="text" name="trust_account_label" value="{{ old('trust_account_label', $trustAccountLabel ?? '') }}" class="w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="e.g. Client trust — Acme PM" />
                    @error('trust_account_label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <label class="block text-xs font-medium text-slate-500">Bank name</label>
                    <input type="text" name="trust_bank_name" value="{{ old('trust_bank_name', $trustBankName ?? '') }}" class="w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    @error('trust_bank_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <label class="block text-xs font-medium text-slate-500">Account number</label>
                    <input type="text" name="trust_account_number" value="{{ old('trust_account_number', $trustAccountNumber ?? '') }}" autocomplete="off" class="w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                    @error('trust_account_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save trust account</button>
                </form>
                <div class="pt-2 border-t border-slate-100 dark:border-slate-700 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Bank / M-Pesa statement reconciliation</label>
                        <p class="mt-1 text-xs text-slate-500">Upload Co-op or Safaricom C2B statements, match receipts, and recover missing credits into Unmatched.</p>
                        <a href="{{ route('property.revenue.statements.index') }}" class="mt-2 inline-flex rounded-lg bg-teal-800 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-900">Open statement upload</a>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Register / cutover imports</label>
                        <p class="mt-1 text-xs text-slate-500">Bulk-load receipt listings, vouchers, bills, and opening balances into {{ config('app.name') }}.</p>
                        <a href="{{ route('property.settings.register_imports') }}" class="mt-2 inline-flex rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Open register imports</a>
                    </div>
                </div>
            </div>

            <form method="post" action="{{ route('property.settings.payments.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-3 min-w-0">
                @csrf
                <input type="hidden" name="save_b2c" value="1" />
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">M-Pesa B2C payouts (outbound)</h2>
                    <span class="text-[11px] font-semibold uppercase tracking-wide {{ !empty($b2cConfigured) ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ !empty($b2cConfigured) ? 'Ready' : 'Not configured' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500">Used for landlord remittances, vendor payments, and payroll lines. Leave blank to keep using server <code class="font-mono">MPESA_B2C_*</code> env values.</p>
                <div>
                    <label class="block text-xs font-medium text-slate-500">B2C shortcode</label>
                    <input type="text" name="mpesa_b2c_shortcode" value="{{ old('mpesa_b2c_shortcode', $b2cShortcode ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="Defaults to collection shortcode" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Initiator name</label>
                    <input type="text" name="mpesa_b2c_initiator_name" value="{{ old('mpesa_b2c_initiator_name', $b2cInitiatorName ?? '') }}" autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Security credential</label>
                    <input type="password" name="mpesa_b2c_security_credential" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="{{ !empty($hasB2cSecurityCredential) ? 'Leave blank to keep saved value' : 'Not set' }}" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Result URL</label>
                    <input type="url" name="mpesa_b2c_result_url" value="{{ old('mpesa_b2c_result_url', $b2cResultUrl ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Timeout URL</label>
                    <input type="url" name="mpesa_b2c_timeout_url" value="{{ old('mpesa_b2c_timeout_url', $b2cTimeoutUrl ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" />
                </div>
                <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save B2C payout settings</button>
            </form>

            <form method="post" action="{{ route('property.settings.payments.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-3 min-w-0">
                @csrf
                <input type="hidden" name="save_auto_receipt" value="1" />
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Automatic payment receipts</h2>
                <p class="text-xs text-slate-500">When a rent payment is matched and settled (bank sync, STK, C2B, or SMS forwarder), send a confirmation to the tenant.</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="payment_auto_receipt_enabled" value="0" />
                    <input type="checkbox" name="payment_auto_receipt_enabled" value="1" id="payment_auto_receipt_enabled" @checked(old('payment_auto_receipt_enabled', ($autoReceiptEnabled ?? true) ? '1' : '0') === '1') class="rounded border-slate-300" />
                    <label for="payment_auto_receipt_enabled" class="text-sm text-slate-700 dark:text-slate-200">Enable automatic receipts</label>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Channel</label>
                    <select name="payment_auto_receipt_channel" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2">
                        @foreach (['sms' => 'SMS', 'email' => 'Email', 'both' => 'SMS + Email'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_auto_receipt_channel', $autoReceiptChannel ?? 'sms') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50">Save receipt settings</button>
            </form>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Tenant payment channel</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Tenant self-pay uses <span class="font-semibold text-slate-900 dark:text-white">M-Pesa STK Push</span>. Agent collections also support bank paybill sync, C2B, and SMS forwarder.</p>
            <p class="mt-2 text-xs text-slate-500">Backend channel remains mapped to the existing STK integration for processing and reconciliation.</p>
        </div>

        <div class="mt-6">
            <a href="{{ route('property.settings.index') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">← Back to settings</a>
        </div>
    </x-property.page>
</x-property-layout>

