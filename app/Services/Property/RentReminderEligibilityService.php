<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmMessage;
use App\Models\PmMessageLog;
use App\Models\PmMessagePreference;
use App\Models\PmMessageRecipient;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RentReminderEligibilityService
{
    public const REASON_NO_OPEN_BALANCE = 'no_open_balance';

    public const REASON_PAID = 'paid';

    public const REASON_INACTIVE = 'inactive';

    public const REASON_NO_STAGE = 'no_stage';

    public const REASON_TENANT_OPTED_OUT = 'tenant_opted_out';

    public const REASON_NO_TENANT = 'no_tenant';

    /**
     * Base query for the daily rent reminder pipeline (active AR with open balance).
     */
    public function reminderInvoiceQuery(CarbonInterface $asOf): Builder
    {
        $scanFrom = $asOf->copy()->subDays(120)->toDateString();
        $scanTo = $asOf->copy()->addDays(5)->toDateString();

        return PmInvoice::query()
            ->openBillable()
            ->whereNotIn('status', [
                PmInvoice::STATUS_PAID,
                PmInvoice::STATUS_CANCELLED,
                PmInvoice::STATUS_DRAFT,
            ])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$scanFrom, $scanTo])
            ->where(function (Builder $q) {
                $q->whereNull('invoice_type')
                    ->orWhereIn('invoice_type', [PmInvoice::TYPE_RENT, PmInvoice::TYPE_MIXED]);
            })
            ->whereHas('unit.property', function (Builder $q) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('properties', 'management_status')) {
                    $q->where('management_status', '!=', \App\Models\Property::MANAGEMENT_ARCHIVED);
                }
            });
    }

    /**
     * Refresh allocations/balance then decide if this invoice may receive a stage today.
     *
     * @param  array<string, mixed>|null  $stage  Output from TenantCommunicationStageService
     * @return array{eligible: bool, reason: ?string, balance: float}
     */
    public function evaluate(PmInvoice $invoice, ?array $stage, CarbonInterface $asOf): array
    {
        $invoice->syncAmountPaidFromAllocations();
        $invoice->refresh();

        if (! $this->isActiveInvoice($invoice)) {
            return [
                'eligible' => false,
                'reason' => $this->inactiveReason($invoice),
                'balance' => $invoice->balanceFloat(),
            ];
        }

        $balance = $invoice->balanceFloat();
        if ($balance <= 0.009) {
            return [
                'eligible' => false,
                'reason' => (string) $invoice->status === PmInvoice::STATUS_PAID
                    ? self::REASON_PAID
                    : self::REASON_NO_OPEN_BALANCE,
                'balance' => $balance,
            ];
        }

        if ($stage === null) {
            return [
                'eligible' => false,
                'reason' => self::REASON_NO_STAGE,
                'balance' => $balance,
            ];
        }

        $internalStage = (string) ($stage['internal_stage'] ?? '');
        if ($internalStage === '') {
            return [
                'eligible' => false,
                'reason' => self::REASON_NO_STAGE,
                'balance' => $balance,
            ];
        }

        $tenantId = (int) ($invoice->pm_tenant_id ?? 0);
        if ($tenantId <= 0) {
            return [
                'eligible' => false,
                'reason' => self::REASON_NO_TENANT,
                'balance' => $balance,
            ];
        }

        if (! $this->tenantAllowsRentReminders($tenantId)) {
            return [
                'eligible' => false,
                'reason' => self::REASON_TENANT_OPTED_OUT,
                'balance' => $balance,
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'balance' => $balance,
        ];
    }

    public function isActiveInvoice(PmInvoice $invoice): bool
    {
        if ($invoice->trashed()) {
            return false;
        }

        return ! in_array((string) $invoice->status, [
            PmInvoice::STATUS_DRAFT,
            PmInvoice::STATUS_CANCELLED,
            PmInvoice::STATUS_PAID,
        ], true);
    }

    public function tenantAllowsRentReminders(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        $rentPref = PmMessagePreference::query()
            ->where('subject_type', 'tenant')
            ->where('subject_id', $tenantId)
            ->where('category', 'rent_reminder')
            ->first();

        if ($rentPref) {
            return (bool) $rentPref->allow_arrears_reminders;
        }

        return true;
    }

    public function tenantAllowsChannel(int $tenantId, string $channel): bool
    {
        if (! $this->tenantAllowsRentReminders($tenantId)) {
            return false;
        }

        $pref = PmMessagePreference::query()
            ->where('subject_type', 'tenant')
            ->where('subject_id', $tenantId)
            ->where('category', 'rent_reminder')
            ->first();

        if (! $pref) {
            return true;
        }

        return match (strtolower($channel)) {
            'sms' => (bool) $pref->allow_sms,
            'email' => (bool) $pref->allow_email,
            default => true,
        };
    }

    /**
     * True when this invoice/channel/stage should not be sent again (delivered or still in flight).
     *
     * @param  array<string, mixed>  $stage
     */
    public function channelStageAlreadySent(int $invoiceId, string $channel, array $stage, CarbonInterface $asOf): bool
    {
        $message = $this->findChannelStageMessage($invoiceId, $channel, $stage, $asOf);

        return $message !== null && $this->messageBlocksRentReminderRetry($message);
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    public function findChannelStageMessage(int $invoiceId, string $channel, array $stage, CarbonInterface $asOf): ?PmMessage
    {
        return PmMessage::query()
            ->withoutGlobalScopes()
            ->where('channel', strtolower($channel))
            ->where('idempotency_key', $this->idempotencyKeyForStage($invoiceId, $channel, $stage, $asOf))
            ->first();
    }

    /**
     * Extra guard using pm_message_logs (covers manual resend and legacy sends).
     */
    public function logShowsRentReminderSentToday(string $toAddress, string $invoiceNo, CarbonInterface $asOf, ?int $excludeLogId = null): bool
    {
        $invoiceNo = strtoupper(trim($invoiceNo));
        if ($invoiceNo === '' || trim($toAddress) === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $toAddress) ?: '';
        $suffix = strlen($digits) >= 9 ? substr($digits, -9) : '';
        if ($suffix === '') {
            return false;
        }

        $query = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->where('delivery_status', 'sent')
            ->whereDate('created_at', $asOf->toDateString())
            ->where(function (Builder $q) use ($suffix) {
                $q->where('to_address', 'like', '%'.$suffix);
            })
            ->where(function (Builder $q) use ($invoiceNo) {
                $q->where('subject', 'like', '%'.$invoiceNo.'%')
                    ->orWhere('body', 'like', '%'.$invoiceNo.'%');
            });

        if ($excludeLogId !== null && $excludeLogId > 0) {
            $query->where('id', '!=', $excludeLogId);
        }

        return $query->exists();
    }

    /**
     * True when this invoice already has a successful SMS to this recipient (any date).
     */
    public function logShowsRentReminderDeliveredForInvoice(string $toAddress, string $invoiceNo, ?int $excludeLogId = null): bool
    {
        $invoiceNo = strtoupper(trim($invoiceNo));
        if ($invoiceNo === '' || trim($toAddress) === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $toAddress) ?: '';
        $suffix = strlen($digits) >= 9 ? substr($digits, -9) : '';
        if ($suffix === '') {
            return false;
        }

        $query = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->whereIn('delivery_status', ['sent', 'delivered'])
            ->where(function (Builder $q) use ($suffix) {
                $q->where('to_address', 'like', '%'.$suffix);
            })
            ->where(function (Builder $q) use ($invoiceNo) {
                $q->where('subject', 'like', '%'.$invoiceNo.'%')
                    ->orWhere('body', 'like', '%'.$invoiceNo.'%');
            });

        if ($excludeLogId !== null && $excludeLogId > 0) {
            $query->where('id', '!=', $excludeLogId);
        }

        return $query->exists();
    }

    public function extractInvoiceNoFromLogText(string $subject, string $body = ''): string
    {
        $haystack = trim($subject).' '.trim($body);
        if (preg_match('/\b(INV-[\w-]+)\b/i', $haystack, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    public function extractInternalStageFromLogText(string $subject, ?string $internalStage = null): string
    {
        $stored = strtoupper(trim((string) $internalStage));
        if ($stored !== '') {
            return $stored;
        }

        $subject = trim($subject);
        if (preg_match('/\[STAFF\|([^|]+)\|/i', $subject, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        if (preg_match('/\b(D[+-]?\d+)\b/i', $subject, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    public function messageBodyHash(string $body): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($body)) ?? '';

        return hash('sha256', $normalized);
    }

    /**
     * @return array{phone_suffix: string, invoice_no: string, internal_stage: string, message_hash: string}
     */
    public function smsIntentFromLog(PmMessageLog $log, ?string $resolvedBody = null): array
    {
        $body = $resolvedBody ?? (string) $log->body;

        return [
            'phone_suffix' => $this->phoneSuffixFromAddress((string) $log->to_address),
            'invoice_no' => $this->extractInvoiceNoFromLogText((string) $log->subject, $body),
            'internal_stage' => $this->extractInternalStageFromLogText((string) $log->subject, (string) $log->internal_stage),
            'message_hash' => $this->messageBodyHash($body),
        ];
    }

    public function phoneSuffixFromAddress(string $toAddress): string
    {
        $digits = preg_replace('/\D+/', '', $toAddress) ?: '';

        return strlen($digits) >= 9 ? substr($digits, -9) : '';
    }

    /**
     * True when a successful SMS already exists for phone + invoice + stage + message body hash.
     */
    public function logShowsSuccessfulSmsForIntent(
        string $toAddress,
        string $invoiceNo,
        string $internalStage,
        string $messageHash,
        ?int $excludeLogId = null
    ): bool {
        return $this->findSuccessfulLogForIntent($toAddress, $invoiceNo, $internalStage, $messageHash, $excludeLogId) !== null;
    }

    /**
     * Any successful SMS for this phone + invoice (ignores stage/body hash).
     * Used for backfill supersede and legacy [RENT] INV-… rows.
     */
    public function findSuccessfulLogForInvoiceAndPhone(string $toAddress, string $invoiceNo, ?int $excludeLogId = null): ?PmMessageLog
    {
        return $this->findSuccessfulLogForIntent($toAddress, $invoiceNo, '', '', $excludeLogId);
    }

    public function findSuccessfulLogForIntent(
        string $toAddress,
        string $invoiceNo,
        string $internalStage,
        string $messageHash,
        ?int $excludeLogId = null
    ): ?PmMessageLog {
        $invoiceNo = strtoupper(trim($invoiceNo));
        $internalStage = strtoupper(trim($internalStage));
        $suffix = $this->phoneSuffixFromAddress($toAddress);
        if ($suffix === '') {
            return null;
        }

        $query = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->whereIn('delivery_status', ['sent', 'delivered'])
            ->where(function (Builder $q) use ($suffix) {
                $q->where('to_address', 'like', '%'.$suffix);
            });

        if ($invoiceNo !== '') {
            $query->where(function (Builder $q) use ($invoiceNo) {
                $q->where('subject', 'like', '%'.$invoiceNo.'%')
                    ->orWhere('body', 'like', '%'.$invoiceNo.'%');
            });
        }

        if ($internalStage !== '') {
            $query->where(function (Builder $q) use ($internalStage) {
                $q->where('internal_stage', $internalStage)
                    ->orWhere('subject', 'like', '%'.$internalStage.'%');
            });
        }

        if ($messageHash !== '') {
            $query->whereNotNull('body')->where('body', '!=', '');
        }

        if ($excludeLogId !== null && $excludeLogId > 0) {
            $query->where('id', '!=', $excludeLogId);
        }

        $candidates = $query->orderByDesc('id')->limit(20)->get();
        foreach ($candidates as $row) {
            if ($messageHash === '' || $this->messageBodyHash((string) $row->body) === $messageHash) {
                return $row;
            }
        }

        return null;
    }

    public function isLogEligibleForSmsResend(PmMessageLog $log): bool
    {
        if ($log->channel !== 'sms') {
            return false;
        }

        return strtolower((string) ($log->delivery_status ?? '')) === 'failed';
    }

    /**
     * @param  Collection<int, PmMessageLog>|iterable<int, PmMessageLog>  $logs
     * @return array<int, array{can_resend: bool, can_bulk_select: bool, label: string, hint: string}>
     */
    public function resendActionsForLogs(iterable $logs): array
    {
        $invoiceNos = [];
        foreach ($logs as $log) {
            $invoiceNo = $this->extractInvoiceNoFromLogText((string) $log->subject, (string) $log->body);
            if ($invoiceNo !== '') {
                $invoiceNos[$invoiceNo] = true;
            }
        }

        $deliveredKeys = $this->deliveredInvoiceKeysForInvoiceNumbers(array_keys($invoiceNos));
        $actions = [];

        foreach ($logs as $log) {
            $actions[(int) $log->id] = $this->smsResendActionForLog($log, $deliveredKeys);
        }

        return $actions;
    }

    /**
     * @param  array<string, true>  $deliveredKeys
     * @return array{can_resend: bool, can_bulk_select: bool, label: string, hint: string}
     */
    public function smsResendActionForLog(PmMessageLog $log, array $deliveredKeys = []): array
    {
        if ($log->channel !== 'sms') {
            return [
                'can_resend' => false,
                'can_bulk_select' => false,
                'label' => '—',
                'hint' => '',
            ];
        }

        $status = strtolower((string) ($log->delivery_status ?? ''));
        $intent = $this->smsIntentFromLog($log);
        $invoiceNo = $intent['invoice_no'];
        $recipientKey = $this->recipientInvoiceKey((string) $log->to_address, $invoiceNo);

        if (! $this->isLogEligibleForSmsResend($log)) {
            if ($status === 'superseded') {
                return [
                    'can_resend' => false,
                    'can_bulk_select' => false,
                    'label' => 'Resolved',
                    'hint' => 'A later send succeeded for this invoice. This failed attempt is kept for audit only.',
                ];
            }

            if (in_array($status, ['sent', 'delivered'], true)) {
                return [
                    'can_resend' => false,
                    'can_bulk_select' => false,
                    'label' => 'Delivered',
                    'hint' => 'This SMS was sent successfully. Resend is hidden to avoid duplicate charges.',
                ];
            }

            return [
                'can_resend' => false,
                'can_bulk_select' => false,
                'label' => 'No resend',
                'hint' => 'Only failed SMS that still need delivery can be resent.',
            ];
        }

        if (
            $this->logShowsSuccessfulSmsForIntent(
                (string) $log->to_address,
                $invoiceNo,
                $intent['internal_stage'],
                $intent['message_hash'],
                (int) $log->id
            )
        ) {
            return [
                'can_resend' => false,
                'can_bulk_select' => false,
                'label' => 'Already sent',
                'hint' => 'A successful SMS for this invoice, stage, and message is already logged. Resend blocked to avoid duplicate charges.',
            ];
        }

        if ($recipientKey !== '' && isset($deliveredKeys[$recipientKey])) {
            return [
                'can_resend' => false,
                'can_bulk_select' => false,
                'label' => 'Already sent',
                'hint' => 'A successful SMS for this invoice is already logged for this recipient (see the SENT row).',
            ];
        }

        if ($status === 'failed') {
            return [
                'can_resend' => true,
                'can_bulk_select' => true,
                'label' => 'Retry send',
                'hint' => 'Provider failed this attempt. Safe to retry once if the tenant did not receive the message.',
            ];
        }

        return [
            'can_resend' => false,
            'can_bulk_select' => false,
            'label' => 'No resend',
            'hint' => 'Resend is only offered for failed SMS that were never delivered for this invoice.',
        ];
    }

    /**
     * @param  list<string>  $invoiceNos
     * @return array<string, true>
     */
    public function deliveredInvoiceKeysForInvoiceNumbers(array $invoiceNos): array
    {
        $invoiceNos = array_values(array_filter(array_map(
            static fn (string $inv): string => strtoupper(trim($inv)),
            $invoiceNos
        )));

        if ($invoiceNos === []) {
            return [];
        }

        $query = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->whereIn('delivery_status', ['sent', 'delivered']);

        $query->where(function (Builder $q) use ($invoiceNos) {
            foreach ($invoiceNos as $invoiceNo) {
                $q->orWhere(function (Builder $inner) use ($invoiceNo) {
                    $inner->where('subject', 'like', '%'.$invoiceNo.'%')
                        ->orWhere('body', 'like', '%'.$invoiceNo.'%');
                });
            }
        });

        $keys = [];
        foreach ($query->get(['to_address', 'subject', 'body']) as $row) {
            $invoiceNo = $this->extractInvoiceNoFromLogText((string) $row->subject, (string) $row->body);
            $key = $this->recipientInvoiceKey((string) $row->to_address, $invoiceNo);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    public function recipientInvoiceKey(string $toAddress, string $invoiceNo): string
    {
        $digits = preg_replace('/\D+/', '', $toAddress) ?: '';
        $suffix = strlen($digits) >= 9 ? substr($digits, -9) : '';
        $invoiceNo = strtoupper(trim($invoiceNo));

        if ($suffix === '' || $invoiceNo === '') {
            return '';
        }

        return $suffix.'|'.$invoiceNo;
    }

    /**
     * Failed SMS rent reminders that still need action (no successful send for same phone + invoice).
     */
    public function applyUnresolvedFailedSmsScope(Builder $q, string $table): void
    {
        $q->where("{$table}.channel", 'sms')
            ->where("{$table}.delivery_status", 'failed');

        $invoiceTokenSql = 'SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT('.$table.'.subject, " ", '.$table.'.body), "INV-", -1), " ", 1)';
        $phoneSuffixSql = 'RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, " ", ""), "+", ""), "-", ""), ".", ""), "/", ""), 9)';

        $q->whereNotExists(function ($sub) use ($table, $invoiceTokenSql, $phoneSuffixSql) {
            $sub->select(DB::raw('1'))
                ->from("{$table} as sent_ok")
                ->where('sent_ok.channel', 'sms')
                ->whereIn('sent_ok.delivery_status', ['sent', 'delivered'])
                ->where(function (QueryBuilder $match) use ($table, $invoiceTokenSql, $phoneSuffixSql) {
                    $match->where(function (QueryBuilder $exact) use ($table) {
                        $exact->whereColumn('sent_ok.to_address', "{$table}.to_address")
                            ->whereColumn('sent_ok.subject', "{$table}.subject");
                    })->orWhere(function (QueryBuilder $relaxed) use ($table, $invoiceTokenSql, $phoneSuffixSql) {
                        $relaxed->whereRaw(sprintf($phoneSuffixSql, 'sent_ok.to_address').' = '.sprintf($phoneSuffixSql, $table.'.to_address'))
                            ->whereRaw('LENGTH('.sprintf($phoneSuffixSql, $table.'.to_address').') >= 9')
                            ->whereRaw(
                                '(sent_ok.subject LIKE CONCAT("%", '.$invoiceTokenSql.') OR sent_ok.body LIKE CONCAT("%", '.$invoiceTokenSql.'))'
                            )
                            ->whereRaw('('.$table.'.subject LIKE "%INV-%" OR '.$table.'.body LIKE "%INV-%")');
                    });
                });
        });
    }

    /**
     * Mark older failed attempts resolved when a later send succeeded (keeps audit row, hides from "Failed" filters).
     *
     * @param  string|list<string>  $toAddresses
     */
    public function supersedeFailedLogsForRecipientInvoice(
        string|array $toAddresses,
        string $invoiceNo,
        ?int $excludeLogId = null,
        ?string $internalStage = null,
        ?string $messageHash = null,
        ?int $successLogId = null,
    ): int {
        $invoiceNo = strtoupper(trim($invoiceNo));
        if ($invoiceNo === '') {
            return 0;
        }

        $suffixes = [];
        foreach ((array) $toAddresses as $address) {
            $suffix = $this->phoneSuffixFromAddress((string) $address);
            if ($suffix !== '') {
                $suffixes[$suffix] = true;
            }
        }

        if ($suffixes === []) {
            return 0;
        }

        $internalStage = strtoupper(trim((string) $internalStage));
        $messageHash = trim((string) $messageHash);

        $query = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->where('delivery_status', 'failed')
            ->where(function (Builder $q) use ($invoiceNo) {
                $q->where('subject', 'like', '%'.$invoiceNo.'%')
                    ->orWhere('body', 'like', '%'.$invoiceNo.'%');
            })
            ->where(function (Builder $q) use ($suffixes) {
                foreach (array_keys($suffixes) as $suffix) {
                    $q->orWhere('to_address', 'like', '%'.$suffix);
                }
            });

        if ($internalStage !== '') {
            $query->where(function (Builder $q) use ($internalStage) {
                $q->where('internal_stage', $internalStage)
                    ->orWhere('subject', 'like', '%'.$internalStage.'%');
            });
        }

        if ($excludeLogId !== null && $excludeLogId > 0) {
            $query->where('id', '!=', $excludeLogId);
        }

        $updated = 0;
        $now = now();
        foreach ($query->get() as $failedLog) {
            if ($messageHash !== '' && $this->messageBodyHash((string) $failedLog->body) !== $messageHash) {
                continue;
            }

            $failedLog->update([
                'delivery_status' => 'superseded',
                'superseded_at' => $now,
                'superseded_by_log_id' => $successLogId,
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Backfill: mark failed SMS logs superseded when a successful send exists for the same invoice + phone.
     *
     * @return array{scanned: int, superseded: int, skipped: int}
     */
    public function supersedeStaleFailedSmsLogs(?CarbonInterface $from = null, ?CarbonInterface $to = null, bool $dryRun = false): array
    {
        $query = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->where('delivery_status', 'failed')
            ->orderBy('id');

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }
        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to->toDateString());
        }

        $scanned = 0;
        $superseded = 0;
        $skipped = 0;

        $query->chunkById(200, function (Collection $rows) use (&$scanned, &$superseded, &$skipped, $dryRun) {
            foreach ($rows as $log) {
                $scanned++;
                $invoiceNo = $this->extractInvoiceNoFromLogText((string) $log->subject, (string) $log->body);
                if ($invoiceNo === '') {
                    $skipped++;

                    continue;
                }

                $successLog = $this->findSuccessfulLogForInvoiceAndPhone(
                    (string) $log->to_address,
                    $invoiceNo,
                    (int) $log->id
                );

                if ($successLog === null) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $superseded++;

                    continue;
                }

                // Backfill: supersede all failed rows for this phone + invoice (legacy bodies may differ).
                $count = $this->supersedeFailedLogsForRecipientInvoice(
                    (string) $log->to_address,
                    $invoiceNo,
                    null,
                    null,
                    null,
                    (int) $successLog->id
                );

                if ($count > 0) {
                    $superseded += $count;
                } else {
                    $skipped++;
                }
            }
        });

        return [
            'scanned' => $scanned,
            'superseded' => $superseded,
            'skipped' => $skipped,
        ];
    }

    /**
     * Whether at least one recipient for this rent reminder message was delivered successfully.
     */
    public function channelStageDeliverySucceeded(PmMessage $message): bool
    {
        $message->loadMissing('recipients');

        return $message->recipients->contains(
            static fn (PmMessageRecipient $recipient): bool => $recipient->status === 'sent'
        );
    }

    /**
     * Block scheduler / manual resend when delivery succeeded or a retry is still pending.
     */
    public function messageBlocksRentReminderRetry(PmMessage $message): bool
    {
        $message->loadMissing('recipients');

        if ($message->recipients->isEmpty()) {
            return false;
        }

        foreach ($message->recipients as $recipient) {
            if ($recipient->status === 'sent') {
                return true;
            }

            if (in_array($recipient->status, ['queued', 'scheduled', 'sending'], true)) {
                return true;
            }

            if ($recipient->status === 'failed' && (int) $recipient->retry_count < (int) $recipient->max_retries) {
                $nextRetry = $recipient->next_retry_at;
                if ($nextRetry === null || $nextRetry->isFuture()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    public function idempotencyKeyForStage(int $invoiceId, string $channel, array $stage, CarbonInterface $asOf): string
    {
        $stageKey = trim((string) ($stage['stage_key'] ?? ''));
        $internal = trim((string) ($stage['internal_stage'] ?? ''));
        $bucket = $stageKey !== '' ? $stageKey : $internal;

        if ($this->stageKeyUsesSendDate($bucket, $internal)) {
            return 'rent:'.$channel.':'.$invoiceId.':'.$bucket.':'.$asOf->toDateString();
        }

        return 'rent:'.$channel.':'.$invoiceId.':'.$bucket;
    }

    /**
     * @deprecated Use idempotencyKeyForStage() with the full stage array.
     */
    public function idempotencyKey(int $invoiceId, string $channel, string $internalStage, string $asOfDate): string
    {
        return $this->idempotencyKeyForStage($invoiceId, $channel, [
            'stage_key' => $internalStage,
            'internal_stage' => $internalStage,
        ], \Illuminate\Support\Carbon::parse($asOfDate)->startOfDay());
    }

    private function stageKeyUsesSendDate(string $stageKey, string $internalStage): bool
    {
        $keys = array_filter([$stageKey, $internalStage]);

        foreach ($keys as $key) {
            if (in_array($key, ['D-3', 'D-1', 'D+0'], true)) {
                return true;
            }
        }

        return false;
    }

    private function inactiveReason(PmInvoice $invoice): string
    {
        return match ((string) $invoice->status) {
            PmInvoice::STATUS_PAID => self::REASON_PAID,
            PmInvoice::STATUS_CANCELLED, PmInvoice::STATUS_DRAFT => self::REASON_INACTIVE,
            default => self::REASON_INACTIVE,
        };
    }
}
