<?php

namespace App\Services;

use App\Models\ClientLead;
use App\Models\ClientLeadActivity;
use App\Models\Employee;
use App\Models\LoanClient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClientLeadIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(?User $viewer): array
    {
        $monthStart = now()->startOfMonth();
        $base = ClientLead::query()->where('client_leads.created_at', '>=', $monthStart);
        $this->scopeClientLeadsForViewer($base, $viewer);

        $totalMtd = (clone $base)->count();

        $converted = (clone $base)->get()->filter(fn (ClientLead $l) => $l->isConvertedByDefinition())->count();

        $conversionRate = $totalMtd > 0 ? round(100 * $converted / $totalMtd, 1) : 0.0;

        $pipelineValue = (float) (clone $base)
            ->where('client_leads.pipeline_status', ClientLead::STATUS_ACTIVE)
            ->sum('expected_loan_amount');

        $totalDisbursed = (float) (clone $base)->get()->sum(fn (ClientLead $l) => (float) ($l->disbursed_amount ?? 0));

        $avgConversionDays = null;
        $disbursedRows = (clone $base)->whereNotNull('disbursed_at')->get();
        if ($disbursedRows->isNotEmpty()) {
            $avgConversionDays = round($disbursedRows->avg(fn (ClientLead $l) => $l->created_at->diffInDays($l->disbursed_at)), 1);
        }

        $idleHours = (int) config('lead_intelligence.idle_hours_without_activity', 24);
        $cut = now()->subHours($idleHours);

        $lateTouch = $this->lateFirstTouchSql();
        $notContacted = (clone $base)
            ->where('client_leads.current_stage', ClientLead::STAGE_NEW)
            ->where('client_leads.created_at', '<=', $cut)
            ->where(function (Builder $q) use ($lateTouch): void {
                $q->whereNull('client_leads.first_activity_at')
                    ->orWhereRaw('client_leads.first_activity_at > '.$lateTouch);
            })
            ->count();

        $staleNoActivity = (clone $base)
            ->where('client_leads.pipeline_status', ClientLead::STATUS_ACTIVE)
            ->whereDoesntHave('activities', function (Builder $q) use ($cut): void {
                $q->where('client_lead_activities.created_at', '>=', $cut);
            })
            ->count();

        return [
            'total_leads_mtd' => $totalMtd,
            'conversion_rate' => $conversionRate,
            'pipeline_value' => $pipelineValue,
            'total_disbursed' => $totalDisbursed,
            'avg_conversion_days' => $avgConversionDays,
            'not_contacted_24h' => $notContacted,
            'stale_no_activity' => $staleNoActivity,
            'idle_hours' => $idleHours,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function leaderboard(?User $viewer): array
    {
        $monthStart = now()->startOfMonth();
        $base = ClientLead::query()
            ->where('client_leads.created_at', '>=', $monthStart)
            ->whereNotNull('client_leads.assigned_officer_id');
        $this->scopeClientLeadsForViewer($base, $viewer);

        $grouped = $base->get()->groupBy('assigned_officer_id');
        $names = User::query()
            ->whereIn('id', $grouped->keys()->filter()->map(fn ($k) => (int) $k)->all())
            ->pluck('name', 'id');

        $collect = $grouped->map(function ($group, $userId) use ($names) {
            $total = $group->count();
            $conv = $group->filter(fn (ClientLead $l) => $l->isConvertedByDefinition())->count();
            $rate = $total > 0 ? round(100 * $conv / $total, 2) : 0.0;
            $disb = $group->sum(fn (ClientLead $l) => (float) ($l->disbursed_amount ?? 0));
            $uid = (int) $userId;

            return [
                'user_id' => $uid,
                'user_name' => (string) ($names[$uid] ?? '—'),
                'total_leads' => $total,
                'converted_leads' => $conv,
                'conversion_rate' => $rate,
                'total_disbursed' => $disb,
                'avg_ticket' => $conv > 0 ? round($disb / $conv, 2) : null,
            ];
        })->values();

        return [
            'top_by_conversion_rate' => $collect->sortByDesc('conversion_rate')->values()->take(5),
            'top_by_disbursed' => $collect->sortByDesc('total_disbursed')->values()->take(5),
            'at_risk_low_conversion' => $collect->sortBy('conversion_rate')->values()->take(5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function insights(?User $viewer): array
    {
        $monthStart = now()->startOfMonth();
        $base = ClientLead::query()->where('client_leads.created_at', '>=', $monthStart);
        $this->scopeClientLeadsForViewer($base, $viewer);

        $bySource = (clone $base)
            ->selectRaw('client_leads.lead_source, SUM(COALESCE(client_leads.disbursed_amount,0)) as disbursed, SUM(COALESCE(client_leads.expected_loan_amount,0)) as expected')
            ->groupBy('client_leads.lead_source')
            ->orderByDesc(DB::raw('SUM(COALESCE(client_leads.disbursed_amount,0))'))
            ->get();

        $scopedLeadIds = (clone $base)->pluck('id');
        $dropReasons = $scopedLeadIds->isEmpty()
            ? collect()
            : DB::table('client_lead_loss_reasons')
                ->whereIn('client_lead_id', $scopedLeadIds->all())
                ->selectRaw('reason, COUNT(*) as c')
                ->groupBy('reason')
                ->orderByDesc('c')
                ->get();

        $lostValue = (float) (clone $base)
            ->where('client_leads.pipeline_status', ClientLead::STATUS_DROPPED)
            ->sum('expected_loan_amount');

        $speed = (clone $base)
            ->whereNotNull('disbursed_at')
            ->whereNotNull('assigned_officer_id')
            ->get()
            ->groupBy('assigned_officer_id')
            ->map(function ($group, $uid) {
                $avg = $group->avg(fn (ClientLead $l) => $l->created_at->diffInDays($l->disbursed_at));

                return [
                    'assigned_officer_id' => (int) $uid,
                    'avg_days_to_disburse' => $avg !== null ? round((float) $avg, 2) : null,
                ];
            })
            ->values()
            ->sortBy('avg_days_to_disburse')
            ->values();

        $speedUids = $speed->pluck('assigned_officer_id')->filter()->unique()->all();
        $speedNames = User::query()->whereIn('id', $speedUids)->pluck('name', 'id');
        $speed = $speed->map(function (array $row) use ($speedNames) {
            $id = (int) ($row['assigned_officer_id'] ?? 0);
            $row['user_name'] = (string) ($speedNames[$id] ?? '—');

            return $row;
        });

        return [
            'value_by_source' => $bySource,
            'drop_reason_counts' => $dropReasons,
            'lost_pipeline_value' => $lostValue,
            'officer_disbursement_speed' => $speed,
        ];
    }

    /**
     * @param  iterable<int, LoanClient>  $loanClientRows
     * @return array<int, list<string>>
     */
    public function alertsForLeads(iterable $loanClientRows): array
    {
        $out = [];
        foreach ($loanClientRows as $row) {
            if (! $row instanceof LoanClient) {
                continue;
            }
            $lead = $row->clientLead;
            if (! $lead) {
                continue;
            }
            $msgs = [];
            $idleH = (int) config('lead_intelligence.idle_hours_without_activity', 24);
            $lastAt = ClientLeadActivity::query()->where('client_lead_id', $lead->id)->max('created_at');
            if ($lead->created_at?->lte(now()->subHours($idleH))) {
                if ($lastAt === null) {
                    $msgs[] = 'No touch logged within '.$idleH.'h of capture.';
                } elseif (Carbon::parse($lastAt)->lt($lead->created_at->copy()->addHours($idleH))) {
                    $msgs[] = 'First touch was after the '.$idleH.'h SLA window.';
                }
            }
            if ($lastAt !== null && Carbon::parse($lastAt)->lt(now()->subDays(3))) {
                $msgs[] = 'No activity in 3+ days.';
            }
            $threshold = (int) (config('lead_intelligence.stuck_stage_days.'.$lead->current_stage, 7));
            if ($lead->stage_entered_at && $lead->stage_entered_at->lt(now()->subDays($threshold))) {
                $msgs[] = 'Stuck in '.$lead->current_stage.' over '.$threshold.' days.';
            }
            $high = (float) config('lead_intelligence.high_value_idle_amount', 50000);
            if ((float) $lead->expected_loan_amount >= $high && $lastAt !== null && Carbon::parse($lastAt)->lt(now()->subDays(2))) {
                $msgs[] = 'High-value lead idle.';
            }
            $latestNext = ClientLeadActivity::query()
                ->where('client_lead_id', $lead->id)
                ->whereNotNull('next_action_date')
                ->orderByDesc('created_at')
                ->value('next_action_date');
            if ($latestNext === null && $lastAt !== null) {
                $msgs[] = 'No follow-up date on the latest activity.';
            }
            if ($msgs !== []) {
                $out[$row->id] = $msgs;
            }
        }

        return $out;
    }

    /**
     * @param  Builder<ClientLead>  $query
     */
    public function scopeClientLeadsForViewer(Builder $query, ?User $viewer): void
    {
        if (! $viewer) {
            return;
        }

        if (($viewer->is_super_admin ?? false) === true) {
            return;
        }

        $role = strtolower(trim((string) ($viewer->effectiveLoanRole() ?? '')));
        if ($role === 'admin' || (bool) env('LOAN_GLOBAL_DATA_ACCESS', false)) {
            return;
        }

        $email = strtolower(trim((string) ($viewer->email ?? '')));
        if ($email === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $employeeId = Employee::query()->whereRaw('LOWER(email) = ?', [$email])->value('id');
        if (! $employeeId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('loanClient', function (Builder $q) use ($employeeId): void {
            $q->where('assigned_employee_id', $employeeId);
        });
    }

    private function lateFirstTouchSql(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "datetime(client_leads.created_at, '+1 day')"
            : 'DATE_ADD(client_leads.created_at, INTERVAL 1 DAY)';
    }
}
