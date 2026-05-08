<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmTenantNotice;
use App\Models\PropertyPortalSetting;
use App\Services\BulkSmsService;
use App\Services\Property\PropertyCommunicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendRentReminders extends Command
{
    protected $signature = 'rent:send-reminders {--date= : Run as-of date (YYYY-MM-DD)} {--force : Send even when not the 1st of the month}';

    protected $description = 'On the 1st of each month (unless --force), email + SMS tenants about open rent invoices for the current month and any arrears.';

    public function handle(PropertyCommunicationService $communication): int
    {
        $enabled = PropertyPortalSetting::isRentReminderAutomationEnabled();
        if (! $enabled) {
            $this->info('Rent reminder automation is off (workflow toggles or PROPERTY_WORKFLOW_AUTOMATION_ENABLED). Skipping reminders.');

            return self::SUCCESS;
        }

        $today = (string) ($this->option('date') ?: now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $this->error('Invalid --date. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $todayCarbon = Carbon::parse($today, (string) config('app.timezone'))->startOfDay();

        if (! $this->option('force') && (int) $todayCarbon->format('j') !== 1) {
            $this->info('Rent reminders are sent on the 1st of each month only. Pass --force to run on other days.');

            return self::SUCCESS;
        }

        $monthStart = $todayCarbon->copy()->startOfMonth()->toDateString();
        $monthEnd = $todayCarbon->copy()->endOfMonth()->toDateString();

        $invoices = PmInvoice::query()
            ->with(['tenant:id,name,email,phone', 'unit:id,label,property_id', 'unit.property:id,name'])
            ->where('status', '!=', PmInvoice::STATUS_DRAFT)
            ->whereColumn('amount_paid', '<', 'amount')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->where('due_date', '<', $monthStart)
                    ->orWhereBetween('issue_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('due_date', [$monthStart, $monthEnd]);
            })
            ->where(function ($q) {
                $q->whereNull('invoice_type')
                    ->orWhereIn('invoice_type', [PmInvoice::TYPE_RENT, PmInvoice::TYPE_MIXED]);
            })
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

            $dueC = $inv->due_date?->copy()->startOfDay();
            if (! $dueC) {
                continue;
            }

            if ($dueC->isAfter($todayCarbon)) {
                $stage = 'MONTHLY '.$todayCarbon->format('Y-m');
            } else {
                $daysPastDue = (int) $dueC->diffInDays($todayCarbon);
                $stage = $daysPastDue <= 4 ? "REMINDER D+{$daysPastDue}" : "ESCALATION D+{$daysPastDue}";
            }

            $tenant = $inv->tenant;
            $place = $inv->unit?->property?->name.'/'.$inv->unit?->label;
            $balance = number_format(max(0, (float) $inv->amount - (float) $inv->amount_paid), 2);
            $due = $dueC->toDateString();

            $subject = "[RENT] {$inv->invoice_no} {$stage}";
            $body = "Rent payment reminder\n\n".
                "Invoice: {$inv->invoice_no}\n".
                "Unit: {$place}\n".
                "Due date: {$due}\n".
                "Balance due: {$balance}\n\n".
                'If you have already paid, please ignore this message.';
            $noticeCreated = false;

            if ($tenant && ! empty($tenant->email)) {
                $message = $communication->sendNow([
                    'created_by_user_id' => null,
                    'channel' => 'email',
                    'category' => 'rent_reminder',
                    'purpose' => 'arrears_reminder',
                    'subject' => $subject,
                    'body' => $body,
                    'idempotency_key' => 'rent:email:'.$inv->id.':'.$stage.':'.$todayCarbon->format('Y-m'),
                    'recipient_type' => 'tenant',
                    'recipient_id' => (int) $tenant->id,
                ], [(string) $tenant->email]);

                if ($message->wasRecentlyCreated) {
                    $sent++;
                    if (! $noticeCreated) {
                        $noticeCreated = $this->createArrearsNoticeIfMissing(
                            $inv,
                            $body,
                            $todayCarbon->year,
                            $todayCarbon->month,
                            $today
                        );
                    }
                }
            }

            if ($tenant && ! empty($tenant->phone)) {
                $smsMsg = "{$subject}\nUnit: {$place}\nDue: {$due}\nBal: {$balance}";
                $phones = app(BulkSmsService::class)->normalizeRecipientList((string) $tenant->phone);
                if ($phones !== []) {
                    $message = $communication->sendNow([
                        'created_by_user_id' => null,
                        'channel' => 'sms',
                        'category' => 'rent_reminder',
                        'purpose' => 'arrears_reminder',
                        'subject' => $subject,
                        'body' => $smsMsg,
                        'idempotency_key' => 'rent:sms:'.$inv->id.':'.$stage.':'.$todayCarbon->format('Y-m'),
                        'recipient_type' => 'tenant',
                        'recipient_id' => (int) $tenant->id,
                    ], $phones);

                    if ($message->wasRecentlyCreated) {
                        $sent++;
                        if (! $noticeCreated) {
                            $noticeCreated = $this->createArrearsNoticeIfMissing(
                                $inv,
                                $smsMsg,
                                $todayCarbon->year,
                                $todayCarbon->month,
                                $today
                            );
                        }
                    }
                }
            }
        }

        $this->info("Rent reminders processed (monthly batch). Sent={$sent}.");

        return self::SUCCESS;
    }

    private function createArrearsNoticeIfMissing(PmInvoice $invoice, string $message, int $year, int $month, string $dueOn): bool
    {
        $invoiceNo = (string) ($invoice->invoice_no ?? '');
        if ($invoiceNo === '') {
            return false;
        }
        $needle = 'Invoice: '.$invoiceNo;
        $exists = PmTenantNotice::query()
            ->where('pm_tenant_id', (int) $invoice->pm_tenant_id)
            ->where('property_unit_id', (int) $invoice->property_unit_id)
            ->where('notice_type', 'arrears_reminder')
            ->whereYear('due_on', $year)
            ->whereMonth('due_on', $month)
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
            'due_on' => $dueOn,
            'notes' => "Auto arrears reminder\nInvoice: {$invoiceNo}\n\n{$message}",
            'created_by_user_id' => null,
        ]);

        return true;
    }
}
