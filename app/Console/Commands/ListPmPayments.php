<?php

namespace App\Console\Commands;

use App\Models\PmPayment;
use Illuminate\Console\Command;

class ListPmPayments extends Command
{
    protected $signature = 'pm:payments-list {--tenant_id= : Filter by pm_tenant_id} {--limit=15 : Max rows}';

    protected $description = 'List latest property payments (pm_payments) to quickly find pending items and their CheckoutRequestID.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? min($limit, 100) : 15;

        $q = PmPayment::query()->orderByDesc('id')->limit($limit);
        $tenantId = $this->option('tenant_id');
        if ($tenantId !== null && $tenantId !== '') {
            $q->where('pm_tenant_id', (int) $tenantId);
        }

        $rows = $q->get(['id', 'pm_tenant_id', 'status', 'amount', 'external_ref', 'paid_at', 'created_at', 'meta']);

        foreach ($rows as $p) {
            $checkout = (string) data_get($p->meta, 'daraja.checkout_request_id', '');
            $this->line(sprintf(
                '#%d tenant=%d %s amount=%s paid_at=%s created=%s checkout=%s',
                (int) $p->id,
                (int) $p->pm_tenant_id,
                (string) $p->status,
                (string) $p->amount,
                $p->paid_at?->format('Y-m-d H:i:s') ?? '—',
                $p->created_at?->format('Y-m-d H:i:s') ?? '—',
                $checkout !== '' ? $checkout : '—',
            ));
        }

        return self::SUCCESS;
    }
}

