<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentReceiptJob;
use App\Models\PmPayment;
use App\Models\PropertyPortalSetting;
use App\Support\Property\MpesaIntegrationConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingPaymentReceipts extends Command
{
    protected $signature = 'payments:dispatch-pending-receipts
        {--limit=100 : Max completed payments to retry per run}
        {--sync : Send immediately instead of queueing}';

    protected $description = 'Send payment received feedback (SMS/email) for settled payments that still need a tenant receipt.';

    public function handle(): int
    {
        if (! PropertyPortalSetting::isPaymentReceiptAutomationEnabled() || ! MpesaIntegrationConfig::autoReceiptEnabled()) {
            $this->info('Payment receipt automation is off. Skipping.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('pm_payments')) {
            $this->warn('pm_payments table is missing.');

            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $query = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('amount', '>', 0)
            ->whereNotNull('pm_tenant_id')
            ->where(function ($q): void {
                $q->whereNull('meta')
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.receipt_notified_at') IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.receipt_notified_at')) = ''");
            })
            ->where(function ($q): void {
                // Skip payments currently claimed by an in-flight receipt job (last 15 minutes).
                $q->whereNull('meta')
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.receipt_claim_at') IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.receipt_claim_at')) = ''")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.receipt_claim_at')) < ?", [now()->subMinutes(15)->toIso8601String()]);
            })
            ->where(function ($q): void {
                $q->whereNull('meta')
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.skip_notification')), 'false') NOT IN ('true','1',1)");
            })
            ->orderBy('id')
            ->limit($limit);

        $payments = $query->get(['id']);
        if ($payments->isEmpty()) {
            $this->info('No pending payment receipts.');

            return self::SUCCESS;
        }

        $queued = 0;
        foreach ($payments as $payment) {
            if ($this->option('sync')) {
                SendPaymentReceiptJob::dispatchSync((int) $payment->id);
            } else {
                SendPaymentReceiptJob::dispatch((int) $payment->id);
            }
            $queued++;
        }

        $this->info("Queued {$queued} payment receipt(s).");

        return self::SUCCESS;
    }
}
