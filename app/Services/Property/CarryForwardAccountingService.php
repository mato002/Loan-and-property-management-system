<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\PmAccountingAuditLog;
use App\Models\PmInvoice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CarryForwardAccountingService
{
    public function isCarryForwardInvoice(PmInvoice $invoice): bool
    {
        return str_starts_with((string) $invoice->description, FinanceFirebreakService::CARRY_FORWARD_PREFIX);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectMissingIssuance(?int $tenantId = null, int $limit = 200): Collection
    {
        return app(AccountingFirebreakService::class)
            ->detectCarryForwardMissingInvoiceIssued($tenantId, $limit);
    }

    /**
     * Idempotently post invoice_issued for one carry-forward invoice.
     */
    public function ensureInvoiceIssued(PmInvoice $invoice, ?User $actor = null, array $origin = []): bool
    {
        if (! $this->isCarryForwardInvoice($invoice)) {
            return false;
        }

        if ((float) $invoice->amount <= 0) {
            return false;
        }

        if (in_array((string) $invoice->status, [PmInvoice::STATUS_DRAFT, PmInvoice::STATUS_CANCELLED], true)) {
            return false;
        }

        if ($this->hasPostedIssuance($invoice)) {
            return false;
        }

        if ($origin !== [] && Schema::hasColumn('pm_invoices', 'carry_forward_origin')) {
            $existing = is_array($invoice->carry_forward_origin) ? $invoice->carry_forward_origin : [];
            if ($existing === []) {
                $invoice->carry_forward_origin = array_merge($origin, [
                    'recorded_at' => now()->toIso8601String(),
                ]);
                $invoice->saveQuietly();
            }
        }

        PropertyAccountingPostingService::postInvoiceIssued($invoice, $actor);

        PmAccountingAuditLog::recordIfNew(
            PmAccountingAuditLog::ACTION_CARRY_FORWARD_GL_ISSUED,
            'pm_invoice',
            (int) $invoice->id,
            [
                'pm_tenant_id' => (int) $invoice->pm_tenant_id,
                'pm_invoice_id' => (int) $invoice->id,
                'summary' => 'Carry-forward invoice '.$invoice->invoice_no.' posted to Trust GL',
                'payload' => [
                    'invoice_no' => (string) $invoice->invoice_no,
                    'amount' => round((float) $invoice->amount, 2),
                    'origin' => $origin,
                ],
            ]
        );

        return true;
    }

    /**
     * @return array{scanned: int, posted: int, skipped: int, errors: array<int, string>}
     */
    public function backfillMissing(
        ?int $tenantId = null,
        ?int $leaseId = null,
        int $limit = 200,
        bool $dryRun = false,
    ): array {
        $query = PmInvoice::query()
            ->withoutGlobalScopes()
            ->billableAr()
            ->where('amount', '>', 0)
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('accounting_journal_batches as b')
                    ->whereColumn('b.source_id', 'pm_invoices.id')
                    ->where('b.source_type', 'pm_invoice')
                    ->where('b.event_type', 'invoice_issued')
                    ->where('b.status', AccountingJournalBatch::STATUS_POSTED);
            })
            ->orderBy('id');

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pm_tenant_id', $tenantId);
        }

        if ($leaseId !== null && $leaseId > 0) {
            $query->where('pm_lease_id', $leaseId);
        }

        $invoices = $query->limit($limit)->get();
        $posted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($invoices as $invoice) {
            if ($dryRun) {
                $posted++;

                continue;
            }

            try {
                $didPost = $this->ensureInvoiceIssued($invoice, Auth::user(), [
                    'source' => 'backfill',
                    'lease_id' => (int) ($invoice->pm_lease_id ?? 0),
                ]);

                if ($didPost) {
                    $posted++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[(int) $invoice->id] = $e->getMessage();
                report($e);
            }
        }

        return [
            'scanned' => $invoices->count(),
            'posted' => $posted,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Compare operational carry-forward open AR vs Trust GL AR from invoice_issued batches.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileOperationalArVsGl(?int $tenantId = null, int $limit = 100): Collection
    {
        if (! Schema::hasTable('accounting_journal_batches') || ! Schema::hasTable('accounting_journal_lines')) {
            return collect();
        }

        $arAccountIds = AccountingChartAccount::query()
            ->whereIn('code', ['1200', '1210'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($arAccountIds === []) {
            return collect();
        }

        $query = PmInvoice::query()
            ->withoutGlobalScopes()
            ->billableAr()
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->where('amount', '>', 0);

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pm_tenant_id', $tenantId);
        }

        $rows = $query
            ->selectRaw('pm_tenant_id as tenant_id')
            ->selectRaw('ROUND(SUM(balance_due), 2) as operational_open_ar')
            ->selectRaw('ROUND(SUM(amount), 2) as operational_total_ar')
            ->groupBy('pm_tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $glByTenant = AccountingJournalLine::query()
            ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
            ->join('pm_invoices as i', function ($join) {
                $join->on('i.id', '=', 'b.source_id')
                    ->where('b.source_type', 'pm_invoice')
                    ->where('b.event_type', 'invoice_issued');
            })
            ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
            ->whereIn('accounting_journal_lines.account_id', $arAccountIds)
            ->where('accounting_journal_lines.debit', '>', 0)
            ->where('i.description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->when($tenantId !== null && $tenantId > 0, fn ($q) => $q->where('i.pm_tenant_id', $tenantId))
            ->groupBy('i.pm_tenant_id')
            ->selectRaw('i.pm_tenant_id as tenant_id, ROUND(SUM(accounting_journal_lines.debit), 2) as gl_ar_issued')
            ->pluck('gl_ar_issued', 'tenant_id');

        $missingByTenant = $this->detectMissingIssuance($tenantId, $limit * 5)
            ->groupBy('tenant_id')
            ->map(fn (Collection $group) => $group->count());

        return $rows
            ->map(function ($row) use ($glByTenant, $missingByTenant) {
                $tenantId = (int) $row->tenant_id;
                $operationalOpen = round((float) $row->operational_open_ar, 2);
                $operationalTotal = round((float) $row->operational_total_ar, 2);
                $glIssued = round((float) ($glByTenant[$tenantId] ?? 0), 2);
                $missingCount = (int) ($missingByTenant[$tenantId] ?? 0);
                $issuedDrift = round($operationalTotal - $glIssued, 2);

                return [
                    'tenant_id' => $tenantId,
                    'operational_open_ar' => $operationalOpen,
                    'operational_total_ar' => $operationalTotal,
                    'gl_ar_issued' => $glIssued,
                    'issued_drift' => $issuedDrift,
                    'missing_issuance_count' => $missingCount,
                    'message' => sprintf(
                        'Tenant #%d CF AR issued drift KES %s (%d missing issuance).',
                        $tenantId,
                        number_format($issuedDrift, 2),
                        $missingCount,
                    ),
                ];
            })
            ->filter(fn (array $row) => abs((float) $row['issued_drift']) > 0.02 || (int) $row['missing_issuance_count'] > 0)
            ->sortByDesc(fn (array $row) => abs((float) $row['issued_drift']))
            ->take($limit)
            ->values();
    }

    public function hasPostedIssuance(PmInvoice $invoice): bool
    {
        return AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', (int) $invoice->id)
            ->where('event_type', 'invoice_issued')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
    }

    /**
     * Reverse GL before operational carry-forward invoice removal.
     */
    public function reverseBeforeInvoiceRemoval(PmInvoice $invoice, ?User $actor = null, ?string $reason = null): void
    {
        if (! $this->isCarryForwardInvoice($invoice)) {
            return;
        }

        PropertyAccountingPostingService::reverseInvoiceIssued(
            $invoice,
            $actor,
            $reason ?: 'Carry-forward invoice removed'
        );
    }
}
