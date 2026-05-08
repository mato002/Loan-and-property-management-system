<?php

namespace App\Services;

use App\Models\PmInvoice;
use App\Models\PmTenant;
use Illuminate\Support\Facades\Schema;

class PaymentMatchingService
{
    /**
     * @param  array<string,mixed>  $transaction
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null}
     */
    public function match(array $transaction, ?int $agentUserId = null): array
    {
        $hasAgentColumn = Schema::hasColumn('pm_tenants', 'agent_user_id');

        $scopeTenants = function ($query) use ($agentUserId, $hasAgentColumn) {
            if ($agentUserId !== null && $agentUserId > 0 && $hasAgentColumn) {
                $query->where('pm_tenants.agent_user_id', $agentUserId);
            }
        };

        $account = $this->normalizeReference((string) ($transaction['account_number'] ?? ''));
        if ($account !== '') {
            $q = PmTenant::query()->whereRaw('UPPER(REPLACE(account_number, " ", "")) = ?', [$account]);
            $scopeTenants($q);
            $tenants = $q->get();
            if ($tenants->count() === 1) {
                return ['tenant_id' => (int) $tenants->first()->id, 'matched_by' => 'account_number', 'reason' => null];
            }
            if ($tenants->count() > 1) {
                return [
                    'tenant_id' => null,
                    'matched_by' => null,
                    'reason' => 'Multiple tenants share this account number; narrow with agent_user_id or fix data.',
                ];
            }
        }

        $phone = $this->normalizePhone((string) ($transaction['phone'] ?? ''));
        if ($phone !== '') {
            $phoneCandidates = $this->phoneCandidates($phone);
            $q = PmTenant::query()
                ->where(function ($outer) use ($phoneCandidates) {
                    foreach ($phoneCandidates as $candidate) {
                        $outer->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") = ?', [$candidate]);
                    }
                });
            $scopeTenants($q);
            $tenants = $q->get();
            if ($tenants->count() === 1) {
                return ['tenant_id' => (int) $tenants->first()->id, 'matched_by' => 'phone', 'reason' => null];
            }
            if ($tenants->count() > 1) {
                return [
                    'tenant_id' => null,
                    'matched_by' => null,
                    'reason' => 'Multiple tenants share this phone; pass agent_user_id on ingest or fix data.',
                ];
            }
        }

        $reference = $this->normalizeReference((string) ($transaction['reference'] ?? ''));
        if ($reference !== '') {
            $q = PmInvoice::query()
                ->withoutGlobalScopes()
                ->whereRaw('UPPER(REPLACE(invoice_no, " ", "")) = ?', [$reference])
                ->whereNotNull('pm_tenant_id')
                ->with('tenant');

            if ($agentUserId !== null && $agentUserId > 0 && $hasAgentColumn) {
                $q->whereHas('tenant', fn ($tq) => $tq->where('agent_user_id', $agentUserId));
            }

            $invoices = $q->get();
            if ($invoices->count() === 1) {
                $tid = (int) $invoices->first()->pm_tenant_id;

                return ['tenant_id' => $tid > 0 ? $tid : null, 'matched_by' => 'reference', 'reason' => null];
            }
            if ($invoices->count() > 1) {
                return [
                    'tenant_id' => null,
                    'matched_by' => null,
                    'reason' => 'Multiple invoices share this reference; fix invoice numbers.',
                ];
            }
        }

        return ['tenant_id' => null, 'matched_by' => null, 'reason' => 'No tenant match by account number, phone, or reference'];
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }
        if (str_starts_with($digits, '7') || str_starts_with($digits, '1')) {
            return '254'.$digits;
        }

        return $digits;
    }

    /**
     * @return list<string>
     */
    private function phoneCandidates(string $normalized): array
    {
        $clean = preg_replace('/\D+/', '', $normalized) ?? '';
        if ($clean === '') {
            return [];
        }

        $candidates = [$clean];
        if (str_starts_with($clean, '254') && strlen($clean) >= 12) {
            $candidates[] = '0'.substr($clean, 3);
        } elseif (str_starts_with($clean, '0') && strlen($clean) >= 10) {
            $candidates[] = '254'.substr($clean, 1);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeReference(string $value): string
    {
        $clean = strtoupper(trim($value));

        return str_replace([' ', '-', '_'], '', $clean);
    }
}
