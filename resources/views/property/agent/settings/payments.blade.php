<x-property-layout>
    <x-slot name="header">Payment configs</x-slot>

    <x-property.page
        title="Payment configs"
        subtitle="M-Pesa API fields and notes. Treat secrets as sensitive — this build stores plain text in the portal settings table."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.roles') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Property users</a>
            <a href="{{ route('property.settings.commission') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Commission</a>
            <a href="{{ route('property.settings.payments') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Payment config</a>
            <a href="{{ route('property.settings.branding') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Branding</a>
            <a href="{{ route('property.settings.rules') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System rules</a>
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup</a>
        </div>

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

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-2 w-full min-w-0">
            <form method="post" action="{{ route('property.settings.payments.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4 min-w-0">
                @csrf
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">M-Pesa (collection)</h2>
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
                <div class="pt-2 border-t border-slate-100 dark:border-slate-700">
                    <label class="block text-xs font-medium text-slate-500">Reconciliation import</label>
                    <form method="post" action="{{ route('property.quick_action.store') }}" enctype="multipart/form-data" class="mt-2 flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                        @csrf
                        <input type="hidden" name="action_key" value="bank_statement_upload" />
                        <input type="file" name="attachment" accept=".csv,.txt,text/csv" required class="text-sm text-slate-600 dark:text-slate-300 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 dark:file:bg-slate-800" />
                        <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 shrink-0">Upload CSV / text</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Custom payment methods</h2>
                <span class="text-xs text-slate-500">{{ count($customPaymentMethods ?? []) }} saved</span>
            </div>
            <p class="text-xs text-slate-500 mb-4">Add your own channels (bank transfer, Stripe, PayPal, cash office, etc.).</p>

            <form method="post" action="{{ route('property.settings.payments.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="save_custom_methods" value="1" />

                @php
                    $rows = old('custom_methods', $customPaymentMethods ?? []);
                    if (! is_array($rows) || $rows === []) {
                        $rows = [['name' => '', 'provider' => '', 'provider_other' => '', 'account' => '', 'instructions' => '']];
                    }
                @endphp

                <div
                    x-data='{
                        methods: @json(array_values($rows)),
                        addMethod() {
                            if (this.methods.length >= 10) return;
                            this.methods.push({ name: "", provider: "", provider_other: "", account: "", instructions: "" });
                        },
                        removeMethod(index) {
                            if (this.methods.length === 1) {
                                this.methods = [{ name: "", provider: "", account: "", instructions: "" }];
                                return;
                            }
                            this.methods.splice(index, 1);
                        }
                    }'
                    class="space-y-3"
                >
                    <template x-for="(method, i) in methods" :key="i">
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Method <span x-text="i + 1"></span></p>
                                <button type="button" @click="removeMethod(i)" class="text-xs font-medium text-red-600 hover:text-red-700">Remove</button>
                            </div>
                            <div class="grid gap-3 md:grid-cols-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500">Method name</label>
                                    <input type="text" x-model="method.name" :name="'custom_methods[' + i + '][name]'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="e.g. Bank transfer" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500">Provider / bank</label>
                                    <select x-model="method.provider" :name="'custom_methods[' + i + '][provider]'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2">
                                        <option value="">Select provider</option>
                                        <option value="KCB Bank">KCB Bank</option>
                                        <option value="Equity Bank">Equity Bank</option>
                                        <option value="Co-op Bank">Co-op Bank</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <input
                                        x-show="method.provider === 'Other'"
                                        x-model="method.provider_other"
                                        :name="'custom_methods[' + i + '][provider_other]'"
                                        type="text"
                                        class="mt-2 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2"
                                        placeholder="Enter bank/provider name"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500">Account / reference</label>
                                    <input type="text" x-model="method.account" :name="'custom_methods[' + i + '][account]'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="e.g. 1234567890" />
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs font-medium text-slate-500">Instructions (optional)</label>
                                <input type="text" x-model="method.instructions" :name="'custom_methods[' + i + '][instructions]'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="e.g. Use tenant phone as payment reference." />
                            </div>
                        </div>
                    </template>

                    <div class="flex items-center justify-between gap-3">
                        <button type="button" @click="addMethod()" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">+ Add fields</button>
                        <span class="text-xs text-slate-500">Up to 10 methods</span>
                    </div>
                </div>

                @error('custom_methods')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save custom methods</button>
            </form>
        </div>

        <div class="mt-6">
            <a href="{{ route('property.settings.index') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">← Back to settings</a>
        </div>
    </x-property.page>
</x-property-layout>

