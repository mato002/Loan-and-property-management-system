<?php

namespace App\Services;

use App\Models\LoanClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LoanClientDedupService
{
    public function __construct(
        private LoanClientIdentifierNormalizer $normalizer
    ) {}

    /**
     * Possible matches on phone or id_number (any kind). Excludes $excludeId.
     *
     * @return Collection<int, LoanClient>
     */
    public function findPotentialDuplicates(?string $phone, ?string $idNumber, ?int $excludeId = null): Collection
    {
        $phoneNorm = $phone !== null && trim($phone) !== '' ? $this->normalizer->normalizePhone(trim($phone)) : null;
        $idTrim = $idNumber !== null && trim($idNumber) !== '' ? trim($idNumber) : null;

        if ($phoneNorm === null && $idTrim === null) {
            return new Collection;
        }

        $digits = $phoneNorm !== null ? (preg_replace('/\D+/', '', $phoneNorm) ?? '') : '';

        $q = LoanClient::query()
            ->select(['id', 'client_number', 'first_name', 'last_name', 'phone', 'id_number', 'kind'])
            ->when($excludeId, fn (Builder $b) => $b->where('id', '!=', $excludeId))
            ->where(function (Builder $w) use ($digits, $idTrim): void {
                if ($idTrim !== null) {
                    $w->where('id_number', $idTrim);
                }
                if ($digits !== '') {
                    if ($idTrim !== null) {
                        $w->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'(',''),')',''),'+','') = ?",
                            [$digits]
                        );
                    } else {
                        $w->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'(',''),')',''),'+','') = ?",
                            [$digits]
                        );
                    }
                }
            });

        return $q->orderBy('id')->limit(25)->get();
    }

    /**
     * Other onboarded clients (kind=client) conflicting on phone or id_number.
     *
     * @return Collection<int, LoanClient>
     */
    public function findConflictingClientsForLeadConversion(LoanClient $lead): Collection
    {
        $phone = $lead->phone;
        $idNumber = $lead->id_number;

        $candidates = $this->findPotentialDuplicates(
            $phone !== null ? (string) $phone : null,
            $idNumber !== null ? (string) $idNumber : null,
            $lead->id
        );

        return $candidates->filter(fn (LoanClient $c) => $c->kind === LoanClient::KIND_CLIENT)->values();
    }

    /**
     * @param  Collection<int, LoanClient>  $matches
     * @return list<string>
     */
    public function formatDuplicateWarnings(Collection $matches): array
    {
        $lines = [];
        foreach ($matches as $row) {
            $lines[] = 'Possible existing client found: '.$row->full_name.' ('.$row->client_number.').';
        }

        return $lines;
    }
}
