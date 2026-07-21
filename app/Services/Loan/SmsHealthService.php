<?php

namespace App\Services\Loan;

use App\Models\LmMessageLog;
use App\Services\Loan\LoanPaymentReminderEligibilityService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class SmsHealthService
{
    public function __construct(
        private readonly LoanPaymentReminderEligibilityService $eligibility,
    ) {
    }

    public function applyUnresolvedFailedSmsScope(Builder $q, string $table): void
    {
        $this->eligibility->applyUnresolvedFailedSmsScope($q, $table);
    }

    public function unresolvedFailedCount(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        string $dateColumn = 'created_at',
    ): int {
        return $this->unresolvedFailedQuery($from, $to, $dateColumn)->count();
    }

    public function unresolvedFailedQuery(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        string $dateColumn = 'created_at',
    ): Builder {
        $table = (new LmMessageLog)->getTable();
        $q = LmMessageLog::query();
        $this->applyUnresolvedFailedSmsScope($q, $table);
        $this->applyDateRange($q, $from, $to, $dateColumn);

        return $q;
    }

    public function unresolvedFailedCountForCommunicationsFilters(array $filters): int
    {
        $filters = $this->normalizeCommunicationsPeriodFilters($filters);
        $from = ! empty($filters['from']) ? now()->parse((string) $filters['from']) : null;
        $to = ! empty($filters['to']) ? now()->parse((string) $filters['to']) : null;

        return $this->unresolvedFailedCount($from, $to);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeCommunicationsPeriodFilters(array $filters): array
    {
        $period = (string) ($filters['period'] ?? '');
        if ($period === 'today') {
            $filters['from'] = now()->toDateString();
            $filters['to'] = now()->toDateString();
        } elseif ($period === 'week') {
            $filters['from'] = now()->startOfWeek()->toDateString();
            $filters['to'] = now()->endOfWeek()->toDateString();
        } elseif ($period === 'month') {
            $filters['from'] = now()->startOfMonth()->toDateString();
            $filters['to'] = now()->endOfMonth()->toDateString();
        }

        return $filters;
    }

    private function applyDateRange(Builder $q, ?CarbonInterface $from, ?CarbonInterface $to, string $dateColumn): void
    {
        if ($from) {
            $q->whereDate($dateColumn, '>=', $from->toDateString());
        }
        if ($to) {
            $q->whereDate($dateColumn, '<=', $to->toDateString());
        }
    }
}
