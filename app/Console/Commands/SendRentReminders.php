<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmMessageLog;
use App\Models\PmTenantNotice;
use App\Models\PropertyPortalSetting;
use App\Services\BulkSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendRentReminders extends Command
{
    protected $signature = 'rent:send-reminders {--date= : Run as-of date (YYYY-MM-DD)}';

    protected $description = 'Send rent reminders and escalation (email + SMS) for open invoices, with grace period up to day 5 then escalation.';

    public function handle(BulkSmsService $sms): int
    {
        $enabled = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        if (! $enabled) {
            $this->info('Auto workflows disabled (workflow_auto_reminders=0). Skipping reminders.');
            return self::SUCCESS;
        }

        $today = (string) ($this->option('date') ?: now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $this->error('Invalid --date. Use YYYY-MM-DD.');
            return self::FAILURE;
        }

        $invoices = PmInvoice::query()
            ->with(['tenant:id,name,email,phone', 'unit:id,label,property_id', 'unit.property:id,name'])
            ->where('status', '!=', PmInvoice::STATUS_DRAFT)
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<=', $today)
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $sent = 0;
        foreach ($invoices as $inv) {
            $inv->refreshComputedStatus();
            if ((float) $inv->amount_paid >= (float) $inv->amount) {
                continue;
            }

            $due = $inv->due_date?->toDateString();
            if (!$due) {
                continue;
            }

            $daysPastDue = now()->parse($today)->diffInDays($inv->due_date, false) * -1;
            $daysPastDue = max(0, (int) $daysPastDue);

            // Grace: due date (D+0) through D+4; escalation from D+5.
            $stage = $daysPastDue <= 4 ? "REMINDER D+{$daysPastDue}" : "ESCALATION D+{$daysPastDue}";

            $tenant = $inv->tenant;
            $place = $inv->unit?->property?->name.'/'.$inv->unit?->label;
            $balance = number_format(max(0, (float) $inv->amount - (float) $inv->amount_paid), 2);

            $subject = "[RENT] {$inv->invoice_no} {$stage}";
            $body = "Rent payment reminder\n\n".
                "Invoice: {$inv->invoice_no}\n".
                "Unit: {$place}\n".
                "Due date: {$due}\n".
                "Balance due: {$balance}\n\n".
                "If you have already paid, please ignore this message.";
            $noticeCreated = false;

            // Dedupe per invoice per day per channel.
            $alreadyEmailed = PmMessageLog::query()
                ->where('channel', 'email')
                ->where('subject', $subject)
                ->whereDate('created_at', $today)
                ->exists();
            $alreadySms = PmMessageLog::query()
                ->where('channel', 'sms')
                ->where('subject', $subject)
                ->whereDate('created_at', $today)
                ->exists();

            // Email
            if (!$alreadyEmailed && $tenant && !empty($tenant->email)) {
                try {
                    Mail::raw($body, function ($m) use ($tenant, $subject) {
                        $m->to($tenant->email)->subject($subject);
                    });
                    PmMessageLog::query()->create([
                        'user_id' => null,
                        'channel' => 'email',
                        'to_address' => $tenant->email,
                        'subject' => $subject,
                        'body' => $body,
                    ]);
                    $sent++;
                    if (! $noticeCreated) {
                        $noticeCreated = $this->createArrearsNoticeIfMissing($inv, $body);
                    }
                } catch (\Throwable $e) {
                    // still continue to SMS
                }
            }

            // SMS (logs + wallet debit via BulkSmsService)
            if (!$alreadySms && $tenant && !empty($tenant->phone)) {
                $phones = $sms->normalizeRecipientList((string) $tenant->phone);
                if ($phones !== []) {
                    $smsMsg = "{$subject}\nUnit: {$place}\nDue: {$due}\nBal: {$balance}";
                    $result = $sms->sendNow($smsMsg, $phones, null, null);
                    if (($result['ok'] ?? false) === true) {
                        PmMessageLog::query()->create([
                            'user_id' => null,
                            'channel' => 'sms',
                            'to_address' => implode(',', $phones),
                            'subject' => $subject,
                            'body' => $smsMsg,
                        ]);
                        $sent++;
                        if (! $noticeCreated) {
                            $noticeCreated = $this->createArrearsNoticeIfMissing($inv, $smsMsg);
                        }
                    }
                }
            }
        }

        $this->info("Rent reminders processed. Sent={$sent}.");
        return self::SUCCESS;
    }

    private function createArrearsNoticeIfMissing(PmInvoice $invoice, string $message): bool
    {
        $invoiceNo = (string) ($invoice->invoice_no ?? '');
        if ($invoiceNo === '') {
            return false;
        }
        $today = now()->toDateString();
        $needle = 'Invoice: '.$invoiceNo;
        $exists = PmTenantNotice::query()
            ->where('pm_tenant_id', (int) $invoice->pm_tenant_id)
            ->where('property_unit_id', (int) $invoice->property_unit_id)
            ->where('notice_type', 'arrears_reminder')
            ->whereDate('due_on', $today)
            ->where('notes', 'like', '%'.$needle.'%')
            ->exists();
        if ($exists) {
            return false;
        }

        PmTenantNotice::query()->create([
            'pm_tenant_id' => (int) $invoice->pm_tenant_id,
            'property_unit_id' => (int) $invoice->property_unit_id,
            'notice_type' => 'arrears_reminder',
            'status' => 'sent',
            'due_on' => $today,
            'notes' => "Auto arrears reminder\nInvoice: {$invoiceNo}\n\n{$message}",
            'created_by_user_id' => null,
        ]);

        return true;
    }
}

