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
            $paused = $this->pauseUnsentReceipts();
            $this->info('Payment receipt automation is off. Skipping.'.($paused > 0 ? " Held {$paused} unsent payment(s)." : ''));

            return self::SUCCESS;
        }

        if (! Schema::hasTable('pm_payments')) {
            $this->warn('pm_payments table is missing.');

            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $monthStart = now()->startOfMonth();
        $query = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('amount', '>', 0)
            ->whereNotNull('pm_tenant_id')
            ->where(function ($q) use ($monthStart): void {
                $q->where('paid_at', '>=', $monthStart)
                    ->orWhere(function ($inner) use ($monthStart): void {
                        $inner->whereNull('paid_at')->where('created_at', '>=', $monthStart);
                    });
            })
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

    /**
     * While receipts are off, hold every completed payment that has not been texted
     * so turning the switch back on does not send the backlog.
     */
    private function pauseUnsentReceipts(): int
    {
        if (! Schema::hasTable('pm_payments')) {
            return 0;
        }

        $payments = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('amount', '>', 0)
            ->whereNotNull('pm_tenant_id')
            ->where(function ($q): void {
                $q->whereNull('meta')
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.receipt_notified_at') IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.receipt_notified_at')) = ''");
            })
            ->where(function ($q): void {
                $q->whereNull('meta')
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.skip_notification')), 'false') NOT IN ('true','1',1)");
            })
            ->orderBy('id')
            ->limit(500)
            ->get();

        $paused = 0;
        foreach ($payments as $payment) {
            $meta = is_array($payment->meta) ? $payment->meta : [];
            if (! empty($meta['receipt_notified_at']) || ! empty($meta['skip_notification'])) {
                continue;
            }

            $meta['skip_notification'] = true;
            $meta['receipt_skip_reason'] = 'receipts_paused';
            unset($meta['receipt_claim_at'], $meta['receipt_claim_by']);
            $payment->update(['meta' => $meta]);
            $paused++;
        }

        return $paused;
    }
}
