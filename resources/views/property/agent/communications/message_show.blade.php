<x-property.workspace
    title="Message #{{ $log->id }}"
    subtitle="Delivery details and full content."
    back-route="property.communications.messages"
    :stats="[
        ['label' => 'Channel', 'value' => strtoupper($log->channel), 'hint' => ''],
        ['label' => 'Status', 'value' => strtoupper((string) ($log->delivery_status ?? 'unknown')), 'hint' => optional($log->sent_at)->format('Y-m-d H:i') ?? 'Not sent'],
        ['label' => 'Recipient', 'value' => $log->to_address, 'hint' => ''],
        ['label' => 'Created', 'value' => optional($log->created_at)->format('Y-m-d H:i') ?? '—', 'hint' => $log->user?->name ?? '—'],
    ]"
    :columns="[]"
>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Subject</p>
                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $log->subject ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Body</p>
                <pre class="mt-1 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $log->body }}</pre>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Delivery</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Status</p>
            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ strtoupper((string) ($log->delivery_status ?? 'unknown')) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Error</p>
            <p class="text-sm text-slate-800 dark:text-slate-200">{{ $log->delivery_error ?: '—' }}</p>

            <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('property.communications.messages', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back to messages</a>
            </div>
        </div>
    </div>
</x-property.workspace>

