<?php

namespace App\Services;

use App\Models\PmInvoice;
use App\Models\PmTenant;

class PaymentMatchingService
{
    /**
     * @param  array<string,mixed>  $transaction
     * @return array{tenant_id:int|null, matched_by:string|null, reason:string|null}
     */
    public function match(array $transaction): array
    {
        $account = $this->normalizeReference((string) ($transaction['account_number'] ?? ''));
        if ($account !== '') {
            $tenant = PmTenant::query()->whereRaw('UPPER(REPLACE(account_number, " ", "")) = ?', [$account])->first();
            if ($tenant) {
                return ['tenant_id' => (int) $tenant->id, 'matched_by' => 'account_number', 'reason' => null];
            }
        }

        $phone = $this->normalizePhone((string) ($transaction['phone'] ?? ''));
        if ($phone !== '') {
            $phoneCandidates = $this->phoneCandidates($phone);
            $tenant = PmTenant::query()
                ->where(function ($q) use ($phoneCandidates) {
                    foreach ($phoneCandidates as $candidate) {
                        $q->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") = ?', [$candidate]);
                    }
                })
                ->first();
            if ($tenant) {
                return ['tenant_id' => (int) $tenant->id, 'matched_by' => 'phone', 'reason' => null];
            }
        }

        $reference = $this->normalizeReference((string) ($transaction['reference'] ?? ''));
        if ($reference !== '') {
            $invoice = PmInvoice::query()->whereRaw('UPPER(REPLACE(invoice_no, " ", "")) = ?', [$reference])->first();
            if ($invoice && $invoice->pm_tenant_id) {
                return ['tenant_id' => (int) $invoice->pm_tenant_id, 'matched_by' => 'reference', 'reason' => null];
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

