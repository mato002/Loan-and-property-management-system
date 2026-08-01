<x-property-layout>
    <x-slot name="header">My SMS Forwarder</x-slot>

    <x-property.page
        title="My SMS Forwarder"
        subtitle="Generate a personal token so the SMS-forwarder app on your office phone tags every M-Pesa payment with your agent id. You only see your own payments; super admin sees everything."
    >
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.forwarder'])
        @error('forwarder')
            <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
        @enderror

        @unless ($tokensTableExists)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-700/50 dark:bg-amber-900/30 dark:text-amber-300">
                The forwarder tokens table is not yet created. Run <code class="font-mono">php artisan migrate</code> on the server, then return here.
            </div>
        @endunless

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Webhook URL + how-to --}}
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">Webhook URL</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Configure this exact URL in your SMS forwarder app. Same for everyone — the token below is what makes it private to you.</p>
                <div class="flex items-center gap-2">
                    <input id="forwarder-url" readonly value="{{ $webhookUrl }}" class="flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-gray-900 text-xs font-mono px-3 py-2" />
                    <button type="button" data-copy-target="#forwarder-url" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Copy</button>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">Custom HTTP header</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Add exactly one custom header on every forwarded request:</p>
                    <pre class="rounded-lg bg-slate-900 text-slate-100 text-xs p-3 overflow-x-auto"><code>X-Agent-Forwarder-Token: &lt;your token below&gt;</code></pre>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">JSON body shape</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Most forwarder apps already send this. Only <code class="font-mono">raw_message</code> is required when no <code class="font-mono">amount</code> is provided.</p>
                    <pre class="rounded-lg bg-slate-900 text-slate-100 text-xs p-3 overflow-x-auto"><code>{
  "provider": "mpesa",
  "source_device": "Office phone 1",
  "raw_message": "TG12ABCDEF Confirmed. Ksh 5,000.00 received from JOHN DOE 0712345678 ...",
  "paid_at": "2026-05-08T10:00:00+03:00"
}</code></pre>
                </div>
            </div>

            {{-- Tokens management --}}
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">My tokens</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Each token is a long random string. Copy it, paste it into your forwarder app's "X-Agent-Forwarder-Token" header. Revoke any token if a phone is lost or stolen.</p>
                    </div>
                </div>

                <form method="post" action="{{ route('property.settings.forwarder.store') }}" class="mt-4 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="flex-1 min-w-[12rem]">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label (optional)</label>
                        <input type="text" name="label" value="{{ old('label') }}" placeholder="e.g. Office phone, June 2026" maxlength="80" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Generate new token</button>
                </form>

                <div class="mt-5 space-y-3">
                    @forelse ($tokens as $row)
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3 {{ $row->revoked_at ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">
                                        {{ $row->label ?: 'Unlabeled token' }}
                                        @if ($row->revoked_at)
                                            <span class="ml-1 inline-flex items-center rounded bg-red-100 text-red-700 px-1.5 py-0.5 text-[10px] font-semibold">Revoked</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        Created {{ optional($row->created_at)->format('Y-m-d H:i') }}
                                        @if ($row->last_used_at)
                                            · Last used {{ optional($row->last_used_at)->diffForHumans() }}
                                            @if ($row->last_used_ip)
                                                from <code class="font-mono">{{ $row->last_used_ip }}</code>
                                            @endif
                                        @else
                                            · Never used yet
                                        @endif
                                    </p>
                                </div>
                                @unless ($row->revoked_at)
                                    <form method="post" action="{{ route('property.settings.forwarder.revoke', $row) }}" data-swal-title="Revoke this token?" data-swal-confirm="Your forwarder app will stop ingesting until you create a new token and update the device.">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Revoke</button>
                                    </form>
                                @endunless
                            </div>

                            @unless ($row->revoked_at)
                                <div class="mt-2 flex items-center gap-2">
                                    <input id="tok-{{ $row->id }}" readonly value="{{ $row->token }}" class="flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-gray-900 text-[11px] font-mono px-2 py-1.5" />
                                    <button type="button" data-copy-target="#tok-{{ $row->id }}" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50">Copy</button>
                                </div>
                            @endunless
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 dark:text-slate-400">No tokens yet. Click "Generate new token" above to create your first one.</p>
                    @endforelse
                </div>

                @if ($activeTokens->count() > 1)
                    <p class="mt-4 text-[11px] text-amber-700 dark:text-amber-400">
                        Tip: usually you want one active token per office phone. Multiple active tokens are fine but harder to audit.
                    </p>
                @endif
            </div>
        </div>

        <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">
            Lost a token? Just revoke it and generate a new one. Old payments already ingested under that token stay attributed to you.
        </p>
    </x-property.page>

    <script>
        document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const sel = btn.getAttribute('data-copy-target');
                const el = document.querySelector(sel);
                if (!el) return;
                el.select();
                el.setSelectionRange(0, 999);
                try { document.execCommand('copy'); } catch (e) {}
                if (navigator.clipboard) { navigator.clipboard.writeText(el.value).catch(function(){}); }
                const original = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = original; }, 1200);
            });
        });
    </script>
</x-property-layout>
