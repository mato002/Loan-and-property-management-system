<?php

namespace App\Services;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PropertyUnit;
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
            $q = PmTenant::query()->whereRaw('UPPER(REPLACE(REPLACE(REPLACE(account_number, " ", ""), "-", ""), "_", "")) = ?', [$account]);
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

            $unitMatch = $this->matchByUnitOrHouseCode($account, $agentUserId);
            if ($unitMatch['tenant_id'] !== null || $unitMatch['reason'] !== null) {
                return $unitMatch;
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
        if ($reference !== '' && $reference !== $account) {
            $unitMatch = $this->matchByUnitOrHouseCode($reference, $agentUserId);
            if ($unitMatch['tenant_id'] !== null || $unitMatch['reason'] !== null) {
                return $unitMatch;
            }
        }

        if ($reference !== '') {
            $q = PmInvoice::query()
                ->withoutGlobalScope('agent_workspace')
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(REPLACE(REPLACE(REPLACE(invoice_no, " ", ""), "-", ""), "_", "")) = ?', [$reference])
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

        return ['tenant_id' => null, 'matched_by' => null, 'reason' => 'No tenant match by account number, unit/house code, phone, or reference'];
    }

    /**
     * Match BillRef patterns like HOUSE-A101, UNIT A14, A101, PROP/A101 against active lease units.
     *
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null}
     */
    private function matchByUnitOrHouseCode(string $normalizedRef, ?int $agentUserId): array
    {
        if ($normalizedRef === '' || ! Schema::hasTable('property_units') || ! Schema::hasTable('pm_lease_unit')) {
            return ['tenant_id' => null, 'matched_by' => null, 'reason' => null];
        }

        $candidates = $this->unitLabelCandidates($normalizedRef);
        if ($candidates === []) {
            return ['tenant_id' => null, 'matched_by' => null, 'reason' => null];
        }

        $hasAgentOnProperty = Schema::hasColumn('properties', 'agent_user_id');

        $unitQuery = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $q->orWhereRaw(
                        'UPPER(REPLACE(REPLACE(REPLACE(label, " ", ""), "-", ""), "_", "")) = ?',
                        [$candidate]
                    );
                }
            });

        if ($agentUserId !== null && $agentUserId > 0 && $hasAgentOnProperty) {
            $unitQuery->whereHas('property', fn ($pq) => $pq->withoutGlobalScopes()->where('agent_user_id', $agentUserId));
        }

        $units = $unitQuery->get(['id', 'label', 'property_id']);
        if ($units->isEmpty()) {
            return ['tenant_id' => null, 'matched_by' => null, 'reason' => null];
        }

        $tenantIds = [];
        foreach ($units as $unit) {
            $lease = PmLease::query()
                ->withoutGlobalScopes()
                ->where('status', PmLease::STATUS_ACTIVE)
                ->whereHas('units', fn ($uq) => $uq->withoutGlobalScopes()->where('property_units.id', $unit->id))
                ->orderByDesc('id')
                ->first(['id', 'pm_tenant_id']);

            if ($lease && (int) $lease->pm_tenant_id > 0) {
                $tenantIds[] = (int) $lease->pm_tenant_id;
            }
        }

        $tenantIds = array_values(array_unique($tenantIds));
        if (count($tenantIds) === 1) {
            return ['tenant_id' => $tenantIds[0], 'matched_by' => 'unit_label', 'reason' => null];
        }
        if (count($tenantIds) > 1) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => 'Multiple active tenants match this house/unit code; use a unique account number.',
            ];
        }

        return ['tenant_id' => null, 'matched_by' => null, 'reason' => null];
    }

    /**
     * @return list<string>
     */
    private function unitLabelCandidates(string $normalizedRef): array
    {
        $ref = strtoupper($normalizedRef);
        $candidates = [$ref];

        // Strip common paybill prefixes: HOUSEA101, UNITA14, APTB4, FLAT12, ROOM3, TNT8890
        foreach (['HOUSE', 'UNIT', 'APT', 'APARTMENT', 'FLAT', 'ROOM', 'HSE', 'BLK', 'BLOCK'] as $prefix) {
            if (str_starts_with($ref, $prefix) && strlen($ref) > strlen($prefix)) {
                $candidates[] = substr($ref, strlen($prefix));
            }
        }

        // PROPNAME/A101 or PROPNAMEA101 trailing segment after last slash/dash already stripped
        if (preg_match('/([A-Z]*\d+[A-Z]*\d*)$/', $ref, $m)) {
            $candidates[] = $m[1];
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $c): bool => strlen($c) >= 1)));
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
