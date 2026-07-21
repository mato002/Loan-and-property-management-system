<?php

namespace App\Services\Property;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AccountingPeriodService
{
    /**
     * Create open periods for the current month and prior months (skips existing names).
     */
    public function openTrailingMonths(int $months = 1, ?int $agentUserId = null): int
    {
        $months = max(1, min(24, $months));
        $created = 0;
        $cursor = now()->startOfMonth();

        DB::transaction(function () use ($months, $agentUserId, &$created, $cursor) {
            for ($i = 0; $i < $months; $i++) {
                if ($this->openMonthIfMissing($cursor, $agentUserId)) {
                    $created++;
                }
                $cursor = $cursor->copy()->subMonth();
            }
        });

        return $created;
    }

    public function openMonthIfMissing(CarbonInterface $month, ?int $agentUserId = null): bool
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $name = $start->format('F Y');

        $exists = $this->periodNameQuery($name, $agentUserId)->exists();
        if ($exists) {
            return false;
        }

        AccountingPeriod::query()->create([
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => AccountingPeriod::STATUS_OPEN,
            'agent_user_id' => $agentUserId,
        ]);

        return true;
    }

    public function findOpenPeriodCovering(CarbonInterface $date, ?int $agentUserId = null): ?AccountingPeriod
    {
        $dateString = Carbon::parse($date)->toDateString();

        return $this->coveringPeriodQuery($dateString, $agentUserId)
            ->where('status', AccountingPeriod::STATUS_OPEN)
            ->orderByRaw('agent_user_id is null asc')
            ->orderByDesc('id')
            ->first();
    }

    public function findPeriodCovering(CarbonInterface $date, ?int $agentUserId = null): ?AccountingPeriod
    {
        $dateString = Carbon::parse($date)->toDateString();

        return $this->coveringPeriodQuery($dateString, $agentUserId)
            ->orderByRaw('agent_user_id is null asc')
            ->orderByDesc('id')
            ->first();
    }

    private function coveringPeriodQuery(string $dateString, ?int $agentUserId)
    {
        return AccountingPeriod::query()
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->where(function ($query) use ($agentUserId) {
                $query->whereNull('agent_user_id');
                if ($agentUserId !== null && $agentUserId > 0) {
                    $query->orWhere('agent_user_id', $agentUserId);
                }
            });
    }

    private function periodNameQuery(string $name, ?int $agentUserId)
    {
        return AccountingPeriod::query()
            ->where('name', $name)
            ->when(
                $agentUserId !== null && $agentUserId > 0,
                fn ($query) => $query->where('agent_user_id', $agentUserId),
                fn ($query) => $query->whereNull('agent_user_id'),
            );
    }
}
