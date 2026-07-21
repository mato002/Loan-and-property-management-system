@if (($smsWallet ?? []) !== [])
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-gray-800/90">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-comment-sms text-teal-600 dark:text-teal-400" aria-hidden="true"></i>
                    SMS wallet
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Top up provider balance and monitor capacity before bulk sends.
                </p>
            </div>
            <a
                href="{{ route('property.communications.sms_provider', absolute: false) }}#sms-topup-card"
                data-turbo-frame="property-main"
                class="text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300"
            >
                Open Provider SMS
            </a>
        </div>

        <div class="space-y-4">
            @include('property.agent.communications.partials.sms_wallet_banner')
            @include('property.agent.communications.partials.sms_topup_card')
        </div>
    </div>
@endif
