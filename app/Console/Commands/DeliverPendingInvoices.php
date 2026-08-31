<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PropertyPortalSetting;
use App\Services\Property\InvoiceTenantDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeliverPendingInvoices extends Command
{
    protected $signature = 'invoices:deliver-pending {--limit=200 : Max invoices to attempt per run}';

    protected $description = 'Email/SMS issued invoices that have not yet been delivered to tenants (manual and auto-generated).';

    public function handle(InvoiceTenantDeliveryService $delivery): int
    {
        if (! PropertyPortalSetting::isInvoiceDeliveryAutomationEnabled()) {
            $this->info('Invoice delivery automation is off (workflow toggles or PROPERTY_WORKFLOW_AUTOMATION_ENABLED). Skipping.');

            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));

        $invoices = PmInvoice::query()
            ->whereNotIn('status', [PmInvoice::STATUS_DRAFT, PmInvoice::STATUS_CANCELLED])
            ->where('amount', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('invoice_kind')
                    ->orWhere('invoice_kind', '!=', PmInvoice::KIND_CREDIT_NOTE);
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('pm_invoice_events')
                    ->whereColumn('pm_invoice_events.pm_invoice_id', 'pm_invoices.id')
                    ->whereIn('pm_invoice_events.event', [
                        PmInvoiceEvent::EVENT_EMAILED,
                        PmInvoiceEvent::EVENT_SMS_SENT,
                    ]);
            })
            ->with(['tenant:id,name,email,phone', 'unit.property:id,name', 'events'])
            ->orderBy('issue_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No undelivered issued invoices found.');

            return self::SUCCESS;
        }

        $delivered = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->tenantDeliveryPending() === false) {
                $skipped++;
                continue;
            }

            if ($delivery->resolveAutoChannel($invoice) === '') {
                $this->line('  Skip '.$invoice->invoice_no.': no reachable tenant contact.');
                $skipped++;
                continue;
            }

            $result = $delivery->deliver($invoice, null, [
                'channel' => 'auto',
                'skip_if_delivered' => true,
            ]);

            if ($result->succeeded()) {
                $delivered++;
                $this->line('  Delivered '.$invoice->invoice_no);
                continue;
            }

            $failed++;
            $this->warn('  Failed '.$invoice->invoice_no.': '.implode(' ', $result->errors));
        }

        $this->info(sprintf(
            'Invoice delivery complete: %d delivered, %d skipped, %d failed (scanned %d).',
            $delivered,
            $skipped,
            $failed,
            $invoices->count(),
        ));

        return self::SUCCESS;
    }
}
