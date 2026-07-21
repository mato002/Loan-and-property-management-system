@php
    use Illuminate\Support\Str;

    $maskAddress = static function (string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }
        if (str_contains($value, '@')) {
            [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');
            if ($local === '') {
                return $value;
            }
            $prefix = substr($local, 0, min(2, strlen($local)));

            return $prefix.str_repeat('*', max(0, strlen($local) - strlen($prefix))).'@'.$domain;
        }
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === '' || strlen($digits) < 4) {
            return '****';
        }

        return substr($digits, 0, 4).str_repeat('*', max(0, strlen($digits) - 6)).substr($digits, -2);
    };

    $resendActions = (array) ($resendActions ?? []);
    $smsErrorPresenter = app(\App\Services\Loan\SmsDeliveryErrorPresenter::class);
@endphp

<form method="post" action="{{ route('loan.communications.messages.bulk', absolute: false) }}">
    @csrf
    @if ($canManageCommunications ?? false)
    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center gap-2 bg-slate-50/80 dark:bg-slate-900/40">
        <select name="bulk_action" required class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-w-[10rem]">
            <option value="">Bulk action…</option>
            <option value="resend_failed">Resend selected SMS (failed only)</option>
            <option value="mark_read">Mark as read</option>
        </select>
        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700" data-swal-confirm="Apply bulk action to selected messages?">Apply to selected</button>
        @error('bulk_action')
            <span class="text-xs text-rose-600">{{ $message }}</span>
        @enderror
        <p class="ml-auto text-xs text-slate-500 dark:text-slate-400 max-w-lg text-right">
            <strong>Resend</strong> only appears on failed rows that were never delivered for that invoice.
            SENT rows show <strong>Delivered</strong>; resolved failures show <strong>Already sent</strong> or <strong>Resolved</strong>.
        </p>
    </div>
    @else
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-500 dark:text-slate-400">
            Bulk resend requires <strong>Manage communications</strong>. Use <strong>View</strong> on a row to inspect delivery details.
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-4 py-3 w-10">
                        @if ($canManageCommunications ?? false)
                            <input type="checkbox" onclick="document.querySelectorAll('.msg-pick-resendable').forEach(el => el.checked = this.checked)" aria-label="Select resendable rows" />
                        @endif
                    </th>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Channel</th>
                    <th class="px-4 py-3">Internal stage</th>
                    <th class="px-4 py-3">Display label</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">To</th>
                    <th class="px-4 py-3">Subject</th>
                    @if (($filters['duplicates'] ?? '') === 'yes')
                        <th class="px-4 py-3">Copies</th>
                    @endif
                    <th class="px-4 py-3">Preview / Error</th>
                    <th class="px-4 py-3">By</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($logs ?? collect()) as $log)
                    @php
                        $parsedStage = app(\App\Services\Loan\LoanClientCommunicationStageService::class)->parseStaffSubject($log->subject);
                        $internalStage = $log->internal_stage ?: ($parsedStage['internal_stage'] ?? '—');
                        $displayStage = $log->display_stage ?: ($parsedStage['display_label'] ?? '—');
                        $status = strtolower((string) ($log->delivery_status ?? 'unknown'));
                        $statusClass = match ($status) {
                            'sent', 'delivered' => 'bg-emerald-50 text-emerald-700',
                            'failed' => 'bg-rose-50 text-rose-700',
                            'queued', 'scheduled' => 'bg-amber-50 text-amber-700',
                            default => 'bg-slate-100 text-slate-700',
                        };
                        $channelClass = $log->channel === 'sms'
                            ? 'bg-emerald-50 text-emerald-700'
                            : ($log->channel === 'email' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-700');
                        $preview = $log->delivery_error
                            ? Str::limit($smsErrorPresenter->forAgent((string) $log->delivery_error), 80)
                            : (($canViewBody ?? false) ? Str::limit(strip_tags((string) $log->body), 64) : '[MASKED]');
                        $action = (array) ($resendActions[(int) $log->id] ?? []);
                        $canResend = (bool) ($action['can_resend'] ?? false);
                        $canBulkSelect = (bool) ($action['can_bulk_select'] ?? false);
                        $actionLabel = (string) ($action['label'] ?? '');
                        $actionHint = (string) ($action['hint'] ?? '');
                    @endphp
                    <tr class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 @if(($filters['duplicates'] ?? '') === 'yes') bg-orange-50/40 dark:bg-orange-950/20 @endif">
                        <td class="px-4 py-3 align-top">
                            @if ($canManageCommunications ?? false)
                                @if ($canBulkSelect)
                                    <input class="msg-pick msg-pick-resendable" type="checkbox" name="selected_ids[]" value="{{ $log->id }}" />
                                @else
                                    <span class="inline-block h-4 w-4" title="Not eligible for bulk resend"></span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $channelClass }}">{{ strtoupper((string) $log->channel) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap font-mono text-xs">{{ $internalStage }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ $displayStage }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ strtoupper($status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ $maskAddress((string) $log->to_address) }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $log->subject ?: '—' }}</td>
                        @if (($filters['duplicates'] ?? '') === 'yes')
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs font-bold text-orange-900 dark:bg-orange-900/50 dark:text-orange-100">
                                    ×{{ (int) ($log->duplicate_group_count ?? 0) }}
                                </span>
                            </td>
                        @endif
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200 max-w-xs">{{ $preview }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <div class="flex flex-wrap gap-1">
                                    <a href="{{ route('loan.communications.messages.show', $log, absolute: false) }}" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/40">View</a>
                                    @if (($canManageCommunications ?? false) && $log->channel === 'sms' && $canResend)
                                        <button type="submit" form="msg-resend-{{ $log->id }}" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40" data-swal-confirm="Resend this SMS now?">Retry</button>
                                    @endif
                                </div>
                                @if ($log->channel === 'sms' && $actionLabel !== '' && $actionLabel !== '—')
                                    <span
                                        class="text-[10px] font-medium {{ $canResend ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400' }}"
                                        title="{{ $actionHint }}"
                                    >{{ $actionLabel }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($filters['duplicates'] ?? '') === 'yes' ? 12 : 11 }}" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                            @if (($filters['duplicates'] ?? '') === 'yes')
                                No duplicate sends match your filters (same recipient, subject, and day).
                            @else
                                No messages match your filters.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</form>

@foreach (($logs ?? collect()) as $log)
    @php
        $action = (array) ($resendActions[(int) $log->id] ?? []);
        $canResend = (bool) ($action['can_resend'] ?? false);
    @endphp
    @if (($canManageCommunications ?? false) && $log->channel === 'sms' && $canResend)
        <form id="msg-resend-{{ $log->id }}" method="post" action="{{ route('loan.communications.messages.resend', $log, absolute: false) }}" class="hidden">@csrf</form>
    @endif
@endforeach
