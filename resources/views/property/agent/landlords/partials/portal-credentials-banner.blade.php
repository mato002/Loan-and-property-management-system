@php
    $creds = $portalCredentials ?? session('landlord_portal_credentials');
    if (! is_array($creds) && isset($landlord)) {
        $creds = session('landlord_portal_credentials_pending_'.(int) $landlord->id);
    }
    $landlordId = isset($landlord) ? (int) $landlord->id : (int) ($creds['landlord_id'] ?? 0);
@endphp
@if (is_array($creds) && (int) ($creds['landlord_id'] ?? 0) === $landlordId && ! empty($creds['temporary_password']))
    <div
        id="landlord-portal-credentials"
        x-data="{
            copiedField: null,
            copy(value, field) {
                if (!value) return;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(value).catch(() => {});
                }
                this.copiedField = field;
                setTimeout(() => { if (this.copiedField === field) this.copiedField = null; }, 1500);
            }
        }"
        class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-950 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100"
    >
        <p class="font-semibold">Portal credentials — copy and share with the landlord</p>
        <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-200">Shown once after reset or onboarding. {{ $creds['delivery_summary'] ?? '' }}</p>

        <dl class="mt-3 space-y-3">
            @if (! empty($creds['email']))
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Login email</dt>
                    <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="text" readonly value="{{ $creds['email'] }}" class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 font-mono text-xs dark:border-emerald-800 dark:bg-gray-900" />
                        <button type="button" @click="copy(@js($creds['email']), 'email')" class="inline-flex min-h-[40px] shrink-0 items-center justify-center rounded-lg border border-emerald-400 bg-white px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100 dark:bg-gray-900 dark:text-emerald-100">
                            <span x-text="copiedField === 'email' ? 'Copied' : 'Copy'"></span>
                        </button>
                    </dd>
                </div>
            @endif
            @if (! empty($creds['phone']))
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Login phone</dt>
                    <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="text" readonly value="{{ $creds['phone'] }}" class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 font-mono text-xs dark:border-emerald-800 dark:bg-gray-900" />
                        <button type="button" @click="copy(@js($creds['phone']), 'phone')" class="inline-flex min-h-[40px] shrink-0 items-center justify-center rounded-lg border border-emerald-400 bg-white px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100 dark:bg-gray-900 dark:text-emerald-100">
                            <span x-text="copiedField === 'phone' ? 'Copied' : 'Copy'"></span>
                        </button>
                    </dd>
                </div>
            @endif
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Temporary password</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input type="text" readonly value="{{ $creds['temporary_password'] }}" class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 font-mono text-sm font-semibold tracking-wide dark:border-emerald-800 dark:bg-gray-900" />
                    <button type="button" @click="copy(@js($creds['temporary_password']), 'password')" class="inline-flex min-h-[40px] shrink-0 items-center justify-center rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                        <span x-text="copiedField === 'password' ? 'Copied' : 'Copy password'"></span>
                    </button>
                </dd>
            </div>
            @if (! empty($creds['login_url']))
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Sign-in URL</dt>
                    <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="text" readonly value="{{ $creds['login_url'] }}" class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 font-mono text-xs break-all dark:border-emerald-800 dark:bg-gray-900" />
                        <button type="button" @click="copy(@js($creds['login_url']), 'url')" class="inline-flex min-h-[40px] shrink-0 items-center justify-center rounded-lg border border-emerald-400 bg-white px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100 dark:bg-gray-900 dark:text-emerald-100">
                            <span x-text="copiedField === 'url' ? 'Copied' : 'Copy URL'"></span>
                        </button>
                    </dd>
                </div>
            @endif
        </dl>
    </div>
@endif
