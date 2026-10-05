<?php

namespace App\Services\Property\BankCollections;

use App\Models\PmTenant;
use Illuminate\Support\Facades\Schema;

/**
 * Matches inbound collection payments to tenants.
 *
 * Order:
 * 1) existing Ac/No (pm_tenants.account_number) — preferred
 * 2) payer phone (unique)
 * 3) payer name together with phone (never name alone)
 *
 * Amount alone and name alone never auto-assign.
 */
final class TenantAccountNumberMatcher
{
    /**
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null, account_number:string}
     */
    public function match(?string $reference, ?int $agentUserId = null): array
    {
        return $this->matchTransaction([
            'account_number' => $reference,
            'reference' => $reference,
        ], $agentUserId);
    }

    /**
     * @param  array{account_number?:?string, reference?:?string, phone?:?string, payer_phone?:?string, payer_name?:?string, name?:?string}  $tx
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null, account_number:string}
     */
    public function matchTransaction(array $tx, ?int $agentUserId = null): array
    {
        $accountRef = trim((string) ($tx['account_number'] ?? $tx['reference'] ?? ''));
        $accountMatch = $this->matchByAccountNumber($accountRef, $agentUserId);
        if ($accountMatch['tenant_id'] !== null || ($accountMatch['reason'] !== null && str_starts_with((string) $accountMatch['reason'], 'Multiple'))) {
            return $accountMatch;
        }

        $phone = trim((string) ($tx['payer_phone'] ?? $tx['phone'] ?? ''));
        $name = trim((string) ($tx['payer_name'] ?? $tx['name'] ?? ''));
        $nameAndPhone = [
            'tenant_id' => null,
            'matched_by' => null,
            'reason' => null,
            'account_number' => '',
        ];

        // Name is only used together with a phone number — never alone.
        if ($phone !== '' && $name !== '') {
            $nameAndPhone = $this->matchByNameAndPhone($name, $phone, $agentUserId);
            if ($nameAndPhone['tenant_id'] !== null || ($nameAndPhone['reason'] !== null && str_starts_with((string) $nameAndPhone['reason'], 'Multiple'))) {
                return $nameAndPhone;
            }
        }

        $phoneMatch = $this->matchByPhone($phone, $agentUserId);
        if ($phoneMatch['tenant_id'] !== null || ($phoneMatch['reason'] !== null && str_starts_with((string) $phoneMatch['reason'], 'Multiple'))) {
            return $phoneMatch;
        }

        $reasons = array_values(array_filter([
            $accountMatch['reason'] ?? null,
            $phone !== '' && $name !== '' ? ($nameAndPhone['reason'] ?? null) : null,
            $phoneMatch['reason'] ?? null,
            $name !== '' && $phone === '' ? 'Name alone is not enough to match a payment.' : null,
        ]));

        return [
            'tenant_id' => null,
            'matched_by' => null,
            'reason' => $reasons !== [] ? implode(' ', $reasons) : 'No tenant match by account number or phone.',
            'account_number' => $accountMatch['account_number'] !== ''
                ? $accountMatch['account_number']
                : $this->normalize($accountRef),
        ];
    }

    /**
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null, account_number:string}
     */
    public function matchByAccountNumber(?string $reference, ?int $agentUserId = null): array
    {
        $account = $this->normalize($reference ?? '');
        if ($account === '') {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => null,
                'account_number' => '',
            ];
        }

        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'account_number')) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => 'Tenant account number field is not available.',
                'account_number' => $account,
            ];
        }

        $query = PmTenant::query()
            ->withoutGlobalScopes()
            ->whereRaw(
                'UPPER(REPLACE(REPLACE(REPLACE(account_number, " ", ""), "-", ""), "_", "")) = ?',
                [$account]
            );
        $this->scopeAgent($query, $agentUserId);

        $tenants = $query->get(['id', 'account_number', 'agent_user_id']);
        if ($tenants->count() === 1) {
            return [
                'tenant_id' => (int) $tenants->first()->id,
                'matched_by' => 'account_number',
                'reason' => null,
                'account_number' => strtoupper(trim((string) $tenants->first()->account_number)),
            ];
        }

        if ($tenants->count() > 1) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => 'Multiple tenants share this account number; leave unmatched for manual review.',
                'account_number' => $account,
            ];
        }

        return [
            'tenant_id' => null,
            'matched_by' => null,
            'reason' => 'No tenant found for account number '.$account.'.',
            'account_number' => $account,
        ];
    }

    /**
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null, account_number:string}
     */
    public function matchByPhone(?string $phone, ?int $agentUserId = null): array
    {
        $normalized = $this->normalizePhone($phone ?? '');
        if ($normalized === '' || ! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'phone')) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => null,
                'account_number' => '',
            ];
        }

        $candidates = $this->phoneCandidates($normalized);
        $query = PmTenant::query()
            ->withoutGlobalScopes()
            ->where(function ($outer) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $outer->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", ""), "/", "") = ?',
                        [$candidate]
                    );
                }
            });
        $this->scopeAgent($query, $agentUserId);

        $tenants = $query->get(['id', 'account_number', 'phone']);
        if ($tenants->count() === 1) {
            return [
                'tenant_id' => (int) $tenants->first()->id,
                'matched_by' => 'phone',
                'reason' => null,
                'account_number' => strtoupper(trim((string) ($tenants->first()->account_number ?? ''))),
            ];
        }

        if ($tenants->count() > 1) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => 'Multiple tenants share this phone; leave unmatched for manual review.',
                'account_number' => '',
            ];
        }

        return [
            'tenant_id' => null,
            'matched_by' => null,
            'reason' => 'No tenant found for phone '.$normalized.'.',
            'account_number' => '',
        ];
    }

    /**
     * Name is allowed only together with phone — never name alone.
     *
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null, account_number:string}
     */
    public function matchByNameAndPhone(?string $name, ?string $phone, ?int $agentUserId = null): array
    {
        $clean = preg_replace('/\s+/', ' ', strtoupper(trim((string) $name))) ?? '';
        $normalizedPhone = $this->normalizePhone($phone ?? '');
        if ($clean === '' || strlen($clean) < 3 || $normalizedPhone === '' || ! Schema::hasTable('pm_tenants')) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => null,
                'account_number' => '',
            ];
        }

        if (! Schema::hasColumn('pm_tenants', 'phone') || ! Schema::hasColumn('pm_tenants', 'name')) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => null,
                'account_number' => '',
            ];
        }

        $candidates = $this->phoneCandidates($normalizedPhone);
        $query = PmTenant::query()
            ->withoutGlobalScopes()
            ->whereRaw('UPPER(TRIM(name)) = ?', [$clean])
            ->where(function ($outer) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $outer->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", ""), "/", "") = ?',
                        [$candidate]
                    );
                }
            });
        $this->scopeAgent($query, $agentUserId);

        $tenants = $query->get(['id', 'account_number', 'name', 'phone']);
        if ($tenants->count() === 1) {
            return [
                'tenant_id' => (int) $tenants->first()->id,
                'matched_by' => 'name_and_phone',
                'reason' => null,
                'account_number' => strtoupper(trim((string) ($tenants->first()->account_number ?? ''))),
            ];
        }

        if ($tenants->count() > 1) {
            return [
                'tenant_id' => null,
                'matched_by' => null,
                'reason' => 'Multiple tenants share this name and phone; leave unmatched for manual review.',
                'account_number' => '',
            ];
        }

        return [
            'tenant_id' => null,
            'matched_by' => null,
            'reason' => 'No tenant found for name and phone together.',
            'account_number' => '',
        ];
    }

    public function normalize(string $value): string
    {
        $value = strtoupper(trim($value));

        return str_replace([' ', '-', '_'], '', $value);
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

    private function scopeAgent($query, ?int $agentUserId): void
    {
        if ($agentUserId !== null && $agentUserId > 0 && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }
    }
}
