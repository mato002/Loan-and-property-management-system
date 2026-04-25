<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingChartCodeGeneratorService
{
    /**
     * @return array{start:int,end:int}
     */
    private function rangeForType(string $accountType): array
    {
        return match ($accountType) {
            AccountingChartAccount::TYPE_ASSET => ['start' => 1000, 'end' => 1999],
            AccountingChartAccount::TYPE_LIABILITY => ['start' => 2000, 'end' => 2999],
            AccountingChartAccount::TYPE_EQUITY => ['start' => 3000, 'end' => 3999],
            AccountingChartAccount::TYPE_INCOME => ['start' => 4000, 'end' => 4999],
            AccountingChartAccount::TYPE_EXPENSE => ['start' => 5000, 'end' => 5999],
            default => ['start' => 9000, 'end' => 9999],
        };
    }

    public function preview(string $accountType, string $accountClass, ?int $parentId): string
    {
        return $this->nextCode($accountType, $accountClass, $parentId, false);
    }

    public function reserve(string $accountType, string $accountClass, ?int $parentId): string
    {
        return $this->nextCode($accountType, $accountClass, $parentId, true);
    }

    private function nextCode(string $accountType, string $accountClass, ?int $parentId, bool $forWrite): string
    {
        $accountType = strtolower(trim($accountType));
        $accountClass = trim($accountClass);
        $range = $this->rangeForType($accountType);
        $start = $range['start'];
        $end = $range['end'];

        $q = AccountingChartAccount::query()
            ->where('account_type', $accountType)
            ->whereRaw('CAST(code AS UNSIGNED) BETWEEN ? AND ?', [$start, $end]);

        if ($forWrite) {
            $q->lockForUpdate();
        }

        $existingCodes = $q->pluck('code')
            ->map(fn ($code) => (int) $code)
            ->filter(fn (int $code) => $code >= $start && $code <= $end)
            ->sort()
            ->values();

        $next = $start;
        if (in_array($accountClass, [AccountingChartAccount::CLASS_PARENT, AccountingChartAccount::CLASS_DETAIL], true) && $parentId) {
            $parent = AccountingChartAccount::query()->find($parentId);
            if ($parent) {
                $next = max($start, ((int) $parent->code) + 1);
            }
        }

        while ($existingCodes->contains($next) && $next <= $end) {
            $next++;
        }

        if ($next > $end) {
            throw ValidationException::withMessages([
                'account_type' => 'No available account code remains in this account type range.',
            ]);
        }

        return (string) $next;
    }
}
