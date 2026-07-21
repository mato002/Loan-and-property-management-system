<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmTenantNotice;
use App\Models\PropertyPortalSetting;
use App\Services\BulkSmsService;
use App\Services\Property\PropertyAgentContactResolver;
use App\Services\Property\PropertyCommunicationService;
use App\Services\Property\PropertyCommunicationTemplateService;
use App\Services\Property\RentReminderEligibilityService;
use App\Services\Property\TenantCommunicationStageService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendRentReminders extends Command
{
    protected $signature = 'rent:send-reminders {--date= : Run as-of date (YYYY-MM-DD)}';

    protected $description = 'Daily rent reminder emails/SMS for unpaid rent invoices when today matches a communication stage (D-3, D-1, due today, overdue buckets).';

    public function handle(
        PropertyCommunicationService $communication,
        TenantCommunicationStageService $stageService,
        PropertyCommunicationTemplateService $templates,
        PropertyAgentContactResolver $agentContacts,
        RentReminderEligibilityService $eligibility,
    ): int {
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

        $invoices = $eligibility->reminderInvoiceQuery($todayCarbon)
            ->with(['tenant:id,name,email,phone', 'unit:id,label,property_id', 'unit.property:id,name'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $sent = 0;
        $skipped = [
            RentReminderEligibilityService::REASON_NO_OPEN_BALANCE => 0,
            RentReminderEligibilityService::REASON_PAID => 0,
            RentReminderEligibilityService::REASON_INACTIVE => 0,
            RentReminderEligibilityService::REASON_NO_STAGE => 0,
            RentReminderEligibilityService::REASON_TENANT_OPTED_OUT => 0,
            RentReminderEligibilityService::REASON_NO_TENANT => 0,
        ];
        $scanned = $invoices->count();

        foreach ($invoices as $inv) {
            $dueC = $inv->due_date?->copy()->startOfDay();
            if (! $dueC) {
                continue;
            }

            $stage = $stageService->resolveFromDueDate($dueC, $todayCarbon);
            $decision = $eligibility->evaluate($inv, $stage, $todayCarbon);

            if (! $decision['eligible']) {
                $reason = (string) ($decision['reason'] ?? RentReminderEligibilityService::REASON_NO_OPEN_BALANCE);
                $skipped[$reason] = (int) ($skipped[$reason] ?? 0) + 1;

                continue;
            }

            $tenant = $inv->tenant;
            $tenantId = (int) ($inv->pm_tenant_id ?? 0);
            $unitName = trim((string) (($inv->unit?->property?->name ?? '').'/'.($inv->unit?->label ?? '')), '/');
            $balance = number_format((float) $decision['balance'], 2);
            $due = $dueC->toDateString();
            $internalStage = (string) $stage['internal_stage'];
            $invoiceNo = (string) $inv->invoice_no;

            $messageContext = $agentContacts->mergeIntoContext([
                'tenant_name' => (string) ($tenant?->name ?? 'Tenant'),
                'invoice_no' => (string) $inv->invoice_no,
                'unit_name' => $unitName !== '' ? $unitName : '—',
                'balance' => $balance,
                'due_date' => $due,
                'stage' => $stage,
            ], $inv);

            $staffSubject = $stageService->staffSubjectLine([
                'internal_stage' => $internalStage,
                'display_label' => $stage['display_label'],
                'invoice_no' => $inv->invoice_no,
            ]);
            $emailPack = $templates->buildRentReminderEmail($messageContext);
            $smsBody = $templates->resolveRentReminderSms($messageContext);
            $asOfDate = $todayCarbon->toDateString();
            $noticeCreated = false;

            $inv->syncAmountPaidFromAllocations();
            if ($inv->balanceFloat() <= 0.009) {
                $skipped[RentReminderEligibilityService::REASON_NO_OPEN_BALANCE]++;

                continue;
            }

            if (
                $tenant
                && ! empty($tenant->email)
                && $eligibility->tenantAllowsChannel($tenantId, 'email')
                && ! $eligibility->channelStageAlreadySent((int) $inv->id, 'email', $stage, $todayCarbon)
            ) {
                $message = $communication->sendNow([
                    'created_by_user_id' => null,
                    'channel' => 'email',
                    'category' => 'rent_reminder',
                    'purpose' => 'arrears_reminder',
                    'subject' => $staffSubject,
                    'body' => $emailPack['body'],
                    'internal_stage' => $internalStage,
                    'display_stage' => $stage['display_label'],
                    'idempotency_key' => $eligibility->idempotencyKeyForStage((int) $inv->id, 'email', $stage, $todayCarbon),
                    'recipient_type' => 'tenant',
                    'recipient_id' => $tenantId,
                ], [(string) $tenant->email]);

                if ($eligibility->channelStageDeliverySucceeded($message)) {
                    $sent++;
                    if (! $noticeCreated) {
                        $noticeCreated = $this->createArrearsNoticeIfMissing(
                            $inv,
                            $emailPack['body'],
                            $today,
                            $internalStage,
                            $stage['display_label']
                        );
                    }
                }
            }

            if (
                $tenant
                && ! empty($tenant->phone)
                && $eligibility->tenantAllowsChannel($tenantId, 'sms')
                && ! $eligibility->channelStageAlreadySent((int) $inv->id, 'sms', $stage, $todayCarbon)
            ) {
                $phones = app(BulkSmsService::class)->normalizeRecipientList((string) $tenant->phone);
                if ($phones !== []) {
                    $smsTo = implode(',', $phones);
                    if ($eligibility->logShowsRentReminderSentToday($smsTo, $invoiceNo, $todayCarbon)) {
                        continue;
                    }

                    $message = $communication->sendNow([
                        'created_by_user_id' => null,
                        'channel' => 'sms',
                        'category' => 'rent_reminder',
                        'purpose' => 'arrears_reminder',
                        'subject' => $staffSubject,
                        'body' => $smsBody,
                        'internal_stage' => $internalStage,
                        'display_stage' => $stage['display_label'],
                        'idempotency_key' => $eligibility->idempotencyKeyForStage((int) $inv->id, 'sms', $stage, $todayCarbon),
                        'recipient_type' => 'tenant',
                        'recipient_id' => $tenantId,
                    ], $phones);

                    if ($eligibility->channelStageDeliverySucceeded($message)) {
                        $sent++;
                        if (! $noticeCreated) {
                            $noticeCreated = $this->createArrearsNoticeIfMissing(
                                $inv,
                                $smsBody,
                                $today,
                                $internalStage,
                                $stage['display_label']
                            );
                        }
                    }
                }
            }
        }

        $skipSummary = collect($skipped)
            ->filter(fn (int $count) => $count > 0)
            ->map(fn (int $count, string $reason) => "{$reason}={$count}")
            ->implode(', ');

        $this->info("Rent reminders for {$today}: invoices={$scanned}, sent={$sent}".($skipSummary !== '' ? ", skipped: {$skipSummary}" : '').'.');

        return self::SUCCESS;
    }

    private function createArrearsNoticeIfMissing(
        PmInvoice $invoice,
        string $message,
        string $dueOn,
        string $internalStage,
        string $displayLabel
    ): bool {
        $invoiceNo = (string) ($invoice->invoice_no ?? '');
        if ($invoiceNo === '') {
            return false;
        }
        $needle = 'Invoice: '.$invoiceNo;
        $stageNeedle = 'Internal stage: '.$internalStage;
        $exists = PmTenantNotice::query()
            ->where('pm_tenant_id', (int) $invoice->pm_tenant_id)
            ->where('property_unit_id', (int) $invoice->property_unit_id)
            ->where('notice_type', 'arrears_reminder')
            ->whereDate('due_on', $dueOn)
            ->where('notes', 'like', '%'.$needle.'%')
            ->where('notes', 'like', '%'.$stageNeedle.'%')
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
            'notes' => "Auto arrears reminder\nInternal stage: {$internalStage}\nDisplay label: {$displayLabel}\nInvoice: {$invoiceNo}\n\n{$message}",
            'created_by_user_id' => null,
        ]);

        return true;
    }
}
