<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.mpesa_c2b') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">C2B inbox</a>
            <a href="{{ route('loan.financial.mpesa_payouts') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">B2C payouts</a>
        </x-slot>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Environment</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ strtoupper($env) }}</p>
                <p class="mt-1 text-xs text-slate-500 break-all">{{ $baseUrl }}</p>
                <p class="mt-3 text-xs text-slate-600">SSL verify: <span class="font-semibold">{{ ($verifySsl ?? true) ? 'on' : 'off' }}</span></p>
                <p class="mt-1 text-xs text-slate-600">B2C requires approval: <span class="font-semibold">{{ ($b2cRequireApproval ?? true) ? 'yes' : 'no' }}</span></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">STK Push</p>
                <p class="mt-2 text-sm font-semibold {{ $stkConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $stkConfigured ? 'Configured' : 'Incomplete' }}</p>
                @if (! empty($stkMissing))
                    <p class="mt-2 text-xs text-amber-800">Missing: {{ implode(', ', $stkMissing) }}</p>
                @endif
                <dl class="mt-3 space-y-1 text-xs text-slate-600">
                    <div class="flex justify-between gap-2"><dt>Shortcode</dt><dd class="font-mono">{{ $masked['stk_shortcode'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Consumer key</dt><dd class="font-mono">{{ $masked['consumer_key'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Passkey</dt><dd class="font-mono">{{ $masked['passkey'] }}</dd></div>
                </dl>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">B2C / C2B</p>
                <p class="mt-2 text-sm">B2C: <span class="font-semibold {{ $b2cConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $b2cConfigured ? 'Configured' : 'Incomplete' }}</span></p>
                <p class="mt-1 text-sm">C2B: <span class="font-semibold {{ $c2bConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $c2bConfigured ? 'Configured' : 'Incomplete' }}</span></p>
                <p class="mt-1 text-sm">Receipt verify: <span class="font-semibold {{ ($statusQueryConfigured ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">{{ ($statusQueryConfigured ?? false) ? 'Configured' : 'Incomplete' }}</span></p>
                @if (! empty($b2cMissing))
                    <p class="mt-2 text-xs text-amber-800">B2C missing: {{ implode('; ', $b2cMissing) }}</p>
                @endif
                @if (! empty($c2bMissing))
                    <p class="mt-1 text-xs text-amber-800">C2B missing: {{ implode('; ', $c2bMissing) }}</p>
                @endif
                @if (! empty($statusQueryMissing))
                    <p class="mt-1 text-xs text-amber-800">Status query missing: {{ implode('; ', $statusQueryMissing) }}</p>
                @endif
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-800">Callback URLs (must be public HTTPS)</h2>
            <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                @foreach ($urls as $label => $url)
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $label) }}</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-slate-800">{{ $url !== '' ? $url : '— not set —' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-800">Register C2B URLs with Safaricom</h2>
            <p class="mt-1 text-sm text-slate-600">Calls Daraja <code class="font-mono text-xs">/mpesa/c2b/v1/registerurl</code> using the Validation and Confirmation URLs above. Run once per shortcode after changing domains.</p>
            <form method="post" action="{{ route('loan.financial.mpesa_settings.register_c2b') }}" class="mt-4" data-swal-confirm="Register Validation and Confirmation URLs with Safaricom for this shortcode?">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]" @disabled(! ($c2bConfigured ?? false))>
                    Register C2B URLs
                </button>
            </form>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
            <p class="font-semibold text-slate-900">Production checklist</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Set <code class="font-mono text-xs">MPESA_ENV=production</code> and production credentials in <code class="font-mono text-xs">.env</code>.</li>
                <li>Point all callback URLs to this host over HTTPS (no localhost).</li>
                <li>Keep <code class="font-mono text-xs">MPESA_VERIFY_SSL=true</code>.</li>
                <li>Register C2B URLs, then test a small Paybill payment.</li>
                <li>For B2C, create a disbursement with payout mode “M-Pesa B2C API”, approve if required, confirm callback posts the ledger.</li>
            </ol>
        </div>
    </x-loan.page>
</x-loan-layout>
