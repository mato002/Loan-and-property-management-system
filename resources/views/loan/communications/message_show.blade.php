<x-loan-layout>
    <x-slot name="header">Communications</x-slot>

<x-loan.page
    title="Message #{{ $log->id }}"
    subtitle="Delivery details and full content."
    back-route="{{ $backRoute ?? 'loan.communications.messages' }}"
    :stats="[
        ['label' => 'Channel', 'value' => strtoupper($log->channel), 'hint' => ''],
        ['label' => 'Status', 'value' => strtoupper((string) ($log->delivery_status ?? 'unknown')), 'hint' => optional($log->sent_at)->format('Y-m-d H:i') ?? 'Not sent'],
        ['label' => 'Recipient', 'value' => $log->to_address, 'hint' => ''],
        ['label' => 'Created', 'value' => optional($log->created_at)->format('Y-m-d H:i') ?? '—', 'hint' => $log->user?->name ?? '—'],
    ]"
    :columns="[]"
>
    @php
        $parsedStage = app(\App\Services\Loan\LoanClientCommunicationStageService::class)->parseStaffSubject($log->subject);
        $internalStage = $log->internal_stage ?: ($parsedStage['internal_stage'] ?? '—');
        $displayStage = $log->display_stage ?: ($parsedStage['display_label'] ?? '—');
    @endphp
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
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Internal stage</p>
                    <p class="text-sm font-mono font-medium text-slate-900 dark:text-white">{{ $internalStage }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Display label</p>
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $displayStage }}</p>
                </div>
            </div>
            @if ($log->template_category)
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Template category</p>
                    <p class="text-sm text-slate-800 dark:text-slate-200">{{ str_replace('_', ' ', $log->template_category) }}</p>
                </div>
            @endif
            <p class="text-xs text-slate-500 dark:text-slate-400">Status</p>
            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ strtoupper((string) ($log->delivery_status ?? 'unknown')) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Error</p>
            <p class="text-sm text-slate-800 dark:text-slate-200">
                @if ($log->delivery_error)
                    {{ app(\App\Services\Loan\SmsDeliveryErrorPresenter::class)->forAgent((string) $log->delivery_error) }}
                @else
                    —
                @endif
            </p>

            @php
                $resendAction = (array) ($resendAction ?? []);
                $canResend = (bool) ($resendAction['can_resend'] ?? false);
            @endphp
            <div class="pt-2 border-t border-slate-200 dark:border-slate-700 space-y-2">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route($backRoute ?? 'loan.communications.messages', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">{{ $backLabel ?? 'Back to messages' }}</a>
                    @if (($canManageCommunications ?? false) && $log->channel === 'sms' && $canResend)
                        <form method="post" action="{{ route('loan.communications.messages.resend', $log, absolute: false) }}" data-swal-confirm="Resend this SMS now?">
                            @csrf
                            <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-700">Retry SMS</button>
                        </form>
                    @endif
                </div>
                @if ($log->channel === 'sms' && ! $canResend && ! empty($resendAction['hint']))
                    <p class="text-xs text-slate-500 dark:text-slate-400 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $resendAction['label'] ?? 'No resend' }}:</span>
                        {{ $resendAction['hint'] }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-loan.page>
</x-loan-layout>

