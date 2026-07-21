<x-loan.page
    title="Payment reminder templates"
    subtitle="Standardized rent communication for SMS, email, WhatsApp, and portal notices. Internal workflow codes (D+0, D+7) stay in staff logs only."
    back-route="loan.communications.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title=""
    empty-hint=""
>
    <x-slot name="above">
        @php
            $canManage = (bool) ($canManageCommunications ?? false);
        @endphp

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Template structure</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Company name, salutation (<span class="font-medium">Dear {tenant_name},</span>), stage purpose, invoice, unit, balance, and agent phone.
                Placeholders: <code class="text-[11px]">{unit_name}</code>, <code class="text-[11px]">{due_date}</code>, <code class="text-[11px]">{balance}</code>, <code class="text-[11px]">{invoice_number}</code>.
            </p>
        </div>

        <div
            class="mt-4 grid gap-4 lg:grid-cols-5"
            x-data="{
                stageKey: @js(old('active_stage', 'D+7')),
                channel: 'sms',
                messages: @js(array_merge($stageMessages, (array) old('messages', []))),
                labels: @js($stageLabels),
                previewUrl: @js(route('loan.communications.payment_templates.preview', absolute: false)),
                csrf: @js(csrf_token()),
                loading: false,
                preview: @js($preview),
                async refreshPreview() {
                    this.loading = true;
                    try {
                        const res = await fetch(this.previewUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify({
                                stage_key: this.stageKey,
                                channel: this.channel,
                                stage_message: this.messages[this.stageKey] || '',
                            }),
                        });
                        if (res.ok) {
                            this.preview = await res.json();
                        }
                    } finally {
                        this.loading = false;
                    }
                },
            }"
            x-init="refreshPreview()"
        >
            <div class="lg:col-span-3 space-y-4">
                @if ($canManage)
                    <form
                        method="post"
                        action="{{ route('loan.communications.payment_templates.store', absolute: false) }}"
                        class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4"
                    >
                        @csrf
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Stage wording</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Sample tenant: <span class="font-medium">Mary Ndugu</span></p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach ($stageKeys as $key)
                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                                    :class="stageKey === @js($key)
                                        ? 'border-blue-600 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200'
                                        : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
                                    @click="stageKey = @js($key); refreshPreview()"
                                >
                                    {{ $stageLabels[$key] ?? $key }}
                                    <span class="ml-1 text-[10px] opacity-60">({{ $key }})</span>
                                </button>
                            @endforeach
                        </div>

                        @foreach ($stageKeys as $key)
                            <div x-show="stageKey === @js($key)" x-cloak>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">
                                    Purpose message — {{ $stageLabels[$key] ?? $key }}
                                </label>
                                <textarea
                                    name="messages[{{ $key }}]"
                                    rows="4"
                                    class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                    x-model="messages[@js($key)]"
                                    @input.debounce.400ms="refreshPreview()"
                                ></textarea>
                            </div>
                        @endforeach

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Save stage wording
                            </button>
                            <button type="button" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50" @click="refreshPreview()">
                                Refresh preview
                            </button>
                        </div>
                    </form>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30 p-4 text-sm text-amber-900 dark:text-amber-100">
                        View-only. Saving requires the <span class="font-medium">Manage communications</span> permission.
                    </div>
                @endif
            </div>

            <div class="lg:col-span-2 space-y-3">
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Live preview</h3>
                        <select
                            x-model="channel"
                            @change="refreshPreview()"
                            class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-xs px-2 py-1"
                        >
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="portal">Portal</option>
                        </select>
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">Sample tenant</dt>
                            <dd class="font-medium text-slate-900 dark:text-white">Mary Ndugu</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">Display label</dt>
                            <dd class="font-medium text-slate-900 dark:text-white" x-text="labels[stageKey] || stageKey"></dd>
                        </div>
                    </dl>

                    <template x-if="preview.subject">
                        <p class="text-xs text-slate-600 dark:text-slate-300">
                            <span class="font-medium">Subject:</span>
                            <span x-text="preview.subject"></span>
                        </p>
                    </template>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-gray-900/60 p-3">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">Generated message</p>
                        <pre class="whitespace-pre-wrap text-xs text-slate-800 dark:text-slate-100 font-sans" x-text="preview.body || ''"></pre>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs" x-show="channel === 'sms'">
                        <div class="rounded-lg border border-slate-200 dark:border-slate-600 p-2">
                            <p class="text-slate-500 dark:text-slate-400">Estimated SMS segments</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white" x-text="preview.sms_segments ?? '—'"></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-600 p-2">
                            <p class="text-slate-500 dark:text-slate-400">Estimated cost</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">
                                <span x-text="(preview.currency || @js($currency)).toString()"></span>
                                <span x-text="Number(preview.estimated_cost || 0).toFixed(2)"></span>
                            </p>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 dark:text-slate-400" x-show="loading">Updating preview…</p>
                </div>
            </div>
        </div>
    </x-slot>
</x-loan.page>
