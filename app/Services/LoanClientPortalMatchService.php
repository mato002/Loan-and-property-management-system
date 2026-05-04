<?php

namespace App\Services;

use App\Models\LoanClient;

class LoanClientPortalMatchService
{
    public function __construct(
        private LoanClientIdentifierNormalizer $normalizer
    ) {}

    /**
     * Match order: id_number, then phone, then email (clients only).
     */
    public function findExistingClientForPortal(?string $idNumber, ?string $phone, ?string $email): ?LoanClient
    {
        $idNumber = $idNumber !== null && trim($idNumber) !== '' ? trim($idNumber) : null;
        $phoneNorm = $phone !== null && trim($phone) !== '' ? $this->normalizer->normalizePhone(trim($phone)) : null;
        $email = $email !== null && trim($email) !== '' ? strtolower(trim($email)) : null;

        $q = LoanClient::query()->clients();

        if ($idNumber !== null) {
            $hit = (clone $q)->where('id_number', $idNumber)->first();
            if ($hit) {
                return $hit;
            }
        }

        if ($phoneNorm !== null) {
            $digits = preg_replace('/\D+/', '', $phoneNorm) ?? '';
            if ($digits !== '') {
                $hit = (clone $q)
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->whereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
                        [$digits]
                    )
                    ->first();
                if ($hit) {
                    return $hit;
                }
            }
        }

        if ($email !== null) {
            return (clone $q)->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
        }

        return null;
    }
}
