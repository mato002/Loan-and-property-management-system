<?php

namespace App\Services;

use App\Models\ClientLead;
use App\Models\ClientLeadActivity;
use App\Models\ClientLeadLossReason;
use App\Models\ClientLeadStatusHistory;
use App\Models\Employee;
use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Models\LoanClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClientLeadPipelineService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function bootstrapNewLead(LoanClient $loanClient, ?User $actor, array $context = []): ClientLead
    {
        return DB::transaction(function () use ($loanClient, $actor, $context): ClientLead {
            $captureKey = (string) ($context['lead_source_key'] ?? '');
            $pipelineSource = $this->mapCaptureSourceToPipelineSource($captureKey);
            $stage = $this->mapLegacyLeadStatusToStage((string) ($loanClient->lead_status ?? 'new'));
            $officerId = $this->resolveOfficerUserIdForLoanClient($loanClient) ?? $actor?->id;

            $expected = round((float) ($context['expected_loan_amount'] ?? 0), 2);

            $lead = ClientLead::query()->create([
                'loan_client_id' => $loanClient->id,
                'lead_source' => $pipelineSource,
                'assigned_officer_id' => $officerId,
                'expected_loan_amount' => $expected,
                'approved_amount' => null,
                'disbursed_amount' => null,
                'current_stage' => $stage,
                'pipeline_status' => $stage === ClientLead::STAGE_DROPPED ? ClientLead::STATUS_DROPPED : ClientLead::STATUS_ACTIVE,
                'stage_entered_at' => now(),
            ]);

            ClientLeadStatusHistory::query()->create([
                'client_lead_id' => $lead->id,
                'from_stage' => null,
                'to_stage' => $stage,
                'changed_by' => $actor?->id,
            ]);

            $followUp = $context['follow_up_date'] ?? null;
            $followUpYmd = $followUp ? (string) $followUp : null;
            $extraNotes = trim((string) ($context['follow_up_notes'] ?? ''));
            $body = 'Lead captured in CRM.';
            if ($extraNotes !== '') {
                $body .= ' '.$extraNotes;
            }
            $this->recordActivity($lead, $actor, 'system', $body, $followUpYmd);

            return $lead->fresh();
        });
    }

    public function ensureForLoanClient(LoanClient $loanClient): ClientLead
    {
        $found = ClientLead::query()->where('loan_client_id', $loanClient->id)->first();
        if ($found) {
            return $found;
        }

        return DB::transaction(function () use ($loanClient): ClientLead {
            $stage = $this->mapLegacyLeadStatusToStage((string) ($loanClient->lead_status ?? 'new'));
            $pipelineSource = $this->inferPipelineSourceFromBiodata((array) ($loanClient->biodata_meta ?? []));

            $lead = ClientLead::query()->create([
                'loan_client_id' => $loanClient->id,
                'lead_source' => $pipelineSource,
                'assigned_officer_id' => $this->resolveOfficerUserIdForLoanClient($loanClient),
                'expected_loan_amount' => 0,
                'approved_amount' => null,
                'disbursed_amount' => null,
                'current_stage' => $stage,
                'pipeline_status' => $stage === ClientLead::STAGE_DROPPED ? ClientLead::STATUS_DROPPED : ClientLead::STATUS_ACTIVE,
                'stage_entered_at' => $loanClient->created_at ?? now(),
            ]);

            ClientLeadStatusHistory::query()->create([
                'client_lead_id' => $lead->id,
                'from_stage' => null,
                'to_stage' => $stage,
                'changed_by' => null,
            ]);

            return $lead;
        });
    }

    public function syncLegacyLeadStatusIntoPipeline(LoanClient $loanClient, ?User $actor = null): void
    {
        if ($loanClient->kind !== LoanClient::KIND_LEAD) {
            return;
        }

        $lead = $this->ensureForLoanClient($loanClient);
        $target = $this->mapLegacyLeadStatusToStage((string) ($loanClient->lead_status ?? 'new'));
        if ($target === $lead->current_stage) {
            return;
        }

        $this->advanceTowardStage($lead, $target, $actor);
    }

    public function transitionToStage(ClientLead $lead, string $toStage, ?User $actor): void
    {
        DB::transaction(function () use ($lead, $toStage, $actor): void {
            $lead->refresh();
            $from = (string) $lead->current_stage;
            if ($from === $toStage) {
                return;
            }

            if (! $this->canMoveDirectly($from, $toStage)) {
                throw new InvalidArgumentException('That stage change is not allowed. Follow the pipeline or move to Dropped.');
            }

            $this->applyStageChange($lead, $from, $toStage, $actor);
        });
    }

    public function advanceTowardStage(ClientLead $lead, string $targetStage, ?User $actor): void
    {
        DB::transaction(function () use ($lead, $targetStage, $actor): void {
            $lead->refresh();
            if ($lead->current_stage === $targetStage) {
                return;
            }

            $path = $this->shortestPath((string) $lead->current_stage, $targetStage);
            if ($path === null || $path === []) {
                throw new InvalidArgumentException('No valid stage path for this update.');
            }

            foreach ($path as $step) {
                $lead->refresh();
                if ($lead->current_stage === $step) {
                    continue;
                }
                $from = (string) $lead->current_stage;
                if (! $this->canMoveDirectly($from, $step)) {
                    throw new InvalidArgumentException('Pipeline integrity check failed.');
                }
                $this->applyStageChange($lead, $from, $step, $actor);
            }
        });
    }

    public function recordActivity(ClientLead $lead, ?User $actor, string $activityType, ?string $notes, ?string $nextActionDateYmd = null): ClientLeadActivity
    {
        return DB::transaction(function () use ($lead, $actor, $activityType, $notes, $nextActionDateYmd): ClientLeadActivity {
            $activity = ClientLeadActivity::query()->create([
                'client_lead_id' => $lead->id,
                'user_id' => $actor?->id,
                'activity_type' => $activityType,
                'notes' => $notes,
                'next_action_date' => $nextActionDateYmd,
            ]);

            $lead->refresh();
            if ($lead->first_activity_at === null) {
                $lead->forceFill(['first_activity_at' => $activity->created_at ?? now()])->save();
            }

            if ($activityType !== 'system' && in_array($lead->current_stage, [ClientLead::STAGE_NEW], true)) {
                try {
                    $this->advanceTowardStage($lead, ClientLead::STAGE_CONTACTED, $actor);
                } catch (\Throwable) {
                }
            }

            return $activity;
        });
    }

    public function recordLoss(ClientLead $lead, ?User $actor, string $reason, ?string $notes): void
    {
        DB::transaction(function () use ($lead, $actor, $reason, $notes): void {
            ClientLeadLossReason::query()->create([
                'client_lead_id' => $lead->id,
                'reason' => $reason,
                'notes' => $notes,
            ]);
            $this->transitionToStage($lead, ClientLead::STAGE_DROPPED, $actor);
        });
    }

    public function syncFromLoanBookApplication(LoanBookApplication $application): void
    {
        $loanClient = $application->loanClient;
        if (! $loanClient) {
            return;
        }

        $lead = ClientLead::query()->where('loan_client_id', $loanClient->id)->first();
        if (! $lead) {
            return;
        }

        if (in_array($application->stage, [LoanBookApplication::STAGE_DECLINED], true)) {
            return;
        }

        $target = match ((string) $application->stage) {
            LoanBookApplication::STAGE_APPROVED => ClientLead::STAGE_APPROVED,
            LoanBookApplication::STAGE_DISBURSED => ClientLead::STAGE_APPROVED,
            default => ClientLead::STAGE_APPLIED,
        };

        try {
            $this->advanceTowardStage($lead, $target, null);
        } catch (\Throwable) {
            return;
        }

        if ($application->stage === LoanBookApplication::STAGE_APPROVED) {
            $lead->refresh();
            $lead->forceFill([
                'approved_amount' => round((float) ($application->amount_requested ?? 0), 2),
            ])->save();
        }

        $this->recordActivity($lead, null, 'system', 'Loan application stage: '.(string) $application->stage.'.', null);
    }

    public function syncFromLoanBookLoan(LoanBookLoan $loan): void
    {
        if ($loan->disbursed_at === null) {
            return;
        }

        $loanClient = $loan->loanClient;
        if (! $loanClient) {
            return;
        }

        $lead = ClientLead::query()->where('loan_client_id', $loanClient->id)->first();
        if (! $lead) {
            return;
        }

        $principal = round((float) ($loan->principal ?? 0), 2);
        $lead->refresh();
        if ($lead->current_stage === ClientLead::STAGE_DISBURSED
            && $lead->disbursed_at
            && $loan->disbursed_at
            && $lead->disbursed_at->equalTo($loan->disbursed_at)
            && abs((float) ($lead->disbursed_amount ?? 0) - $principal) < 0.01) {
            return;
        }

        try {
            $this->advanceTowardStage($lead, ClientLead::STAGE_DISBURSED, null);
        } catch (\Throwable) {
            $lead->refresh();
            $from = (string) $lead->current_stage;
            if ($from !== ClientLead::STAGE_DISBURSED) {
                $lead->forceFill([
                    'current_stage' => ClientLead::STAGE_DISBURSED,
                    'stage_entered_at' => now(),
                    'pipeline_status' => ClientLead::STATUS_CONVERTED,
                ])->save();
                ClientLeadStatusHistory::query()->create([
                    'client_lead_id' => $lead->id,
                    'from_stage' => $from,
                    'to_stage' => ClientLead::STAGE_DISBURSED,
                    'changed_by' => null,
                ]);
                $this->mirrorStageToLoanClientLeadStatus($lead->loadMissing('loanClient')->loanClient, ClientLead::STAGE_DISBURSED);
            }
        }

        $lead->refresh();
        $lead->forceFill([
            'disbursed_amount' => $principal,
            'disbursed_at' => $loan->disbursed_at,
            'pipeline_status' => ClientLead::STATUS_CONVERTED,
        ])->save();

        $this->recordActivity($lead, null, 'system', 'Loan disbursed (principal '.number_format($principal, 2).').', null);
    }

    public function resolveRoundRobinEmployeeId(): ?int
    {
        $cfg = (array) config('lead_intelligence.round_robin', []);
        if (! ($cfg['enabled'] ?? false)) {
            return null;
        }

        $userIds = array_values(array_filter(array_map('intval', $cfg['user_ids'] ?? [])));
        if ($userIds === []) {
            return null;
        }

        $monthStart = now()->startOfMonth();
        $counts = ClientLead::query()
            ->selectRaw('assigned_officer_id as uid, COUNT(*) as c')
            ->whereIn('assigned_officer_id', $userIds)
            ->where('created_at', '>=', $monthStart)
            ->groupBy('assigned_officer_id')
            ->pluck('c', 'uid');

        $bestUserId = null;
        $bestCount = PHP_INT_MAX;
        foreach ($userIds as $uid) {
            $c = (int) ($counts[$uid] ?? 0);
            if ($c < $bestCount || ($c === $bestCount && ($bestUserId === null || $uid < $bestUserId))) {
                $bestCount = $c;
                $bestUserId = $uid;
            }
        }

        if (! $bestUserId) {
            return null;
        }

        $user = User::query()->find($bestUserId);
        if (! $user) {
            return null;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email === '') {
            return null;
        }

        return Employee::query()->whereRaw('LOWER(email) = ?', [$email])->value('id');
    }

    public function resolveOfficerUserIdForLoanClient(LoanClient $loanClient): ?int
    {
        $empId = $loanClient->assigned_employee_id;
        if (! $empId) {
            return null;
        }

        $email = Employee::query()->whereKey($empId)->value('email');
        $email = $email ? strtolower(trim((string) $email)) : '';

        if ($email === '') {
            return null;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->value('id');
    }

    private function applyStageChange(ClientLead $lead, string $from, string $to, ?User $actor): void
    {
        $updates = [
            'current_stage' => $to,
            'stage_entered_at' => now(),
        ];

        if ($to === ClientLead::STAGE_DROPPED) {
            $updates['pipeline_status'] = ClientLead::STATUS_DROPPED;
        } elseif ($to === ClientLead::STAGE_DISBURSED || (float) ($lead->disbursed_amount ?? 0) > 0) {
            $updates['pipeline_status'] = ClientLead::STATUS_CONVERTED;
        } else {
            $updates['pipeline_status'] = ClientLead::STATUS_ACTIVE;
        }

        $lead->forceFill($updates)->save();

        ClientLeadStatusHistory::query()->create([
            'client_lead_id' => $lead->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'changed_by' => $actor?->id,
        ]);

        $loanClient = $lead->loadMissing('loanClient')->loanClient;
        $this->mirrorStageToLoanClientLeadStatus($loanClient, $to);
    }

    private function mirrorStageToLoanClientLeadStatus(?LoanClient $loanClient, string $stage): void
    {
        if (! $loanClient || $loanClient->kind !== LoanClient::KIND_LEAD) {
            return;
        }

        $leadStatus = match ($stage) {
            ClientLead::STAGE_NEW => 'new',
            ClientLead::STAGE_CONTACTED => 'contacted',
            ClientLead::STAGE_INTERESTED, ClientLead::STAGE_APPLIED, ClientLead::STAGE_APPROVED, ClientLead::STAGE_DISBURSED => 'qualified',
            ClientLead::STAGE_DROPPED => 'lost',
            default => 'new',
        };

        if ((string) ($loanClient->lead_status ?? '') !== $leadStatus) {
            $loanClient->forceFill(['lead_status' => $leadStatus])->save();
        }
    }

    private function canMoveDirectly(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        if ($to === ClientLead::STAGE_DROPPED) {
            return ! in_array($from, [ClientLead::STAGE_DROPPED], true);
        }

        if ($from === ClientLead::STAGE_DROPPED && $to === ClientLead::STAGE_NEW) {
            return true;
        }

        $allowed = (array) config('lead_intelligence.allowed_stage_transitions.'.$from, []);

        return in_array($to, $allowed, true);
    }

    /**
     * @return list<string>|null
     */
    private function shortestPath(string $from, string $to): ?array
    {
        if ($from === $to) {
            return [];
        }

        $queue = [[$from, []]];
        $visited = [$from => true];

        while ($queue !== []) {
            [$node, $path] = array_shift($queue);
            $neighbors = $this->neighborStages($node);
            foreach ($neighbors as $next) {
                if (isset($visited[$next])) {
                    continue;
                }
                $newPath = array_merge($path, [$next]);
                if ($next === $to) {
                    return $newPath;
                }
                $visited[$next] = true;
                $queue[] = [$next, $newPath];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function neighborStages(string $from): array
    {
        $edges = array_values(array_unique((array) config('lead_intelligence.allowed_stage_transitions.'.$from, [])));

        if (! in_array($from, [ClientLead::STAGE_DROPPED, ClientLead::STAGE_DISBURSED], true)) {
            $edges[] = ClientLead::STAGE_DROPPED;
        }

        return $edges;
    }

    public function mapLegacyLeadStatusToStage(string $leadStatus): string
    {
        return match (strtolower(trim($leadStatus))) {
            'contacted' => ClientLead::STAGE_CONTACTED,
            'qualified' => ClientLead::STAGE_INTERESTED,
            'not_qualified', 'lost' => ClientLead::STAGE_DROPPED,
            default => ClientLead::STAGE_NEW,
        };
    }

    private function mapCaptureSourceToPipelineSource(string $key): string
    {
        $map = (array) config('lead_intelligence.capture_source_to_pipeline_source', []);

        return (string) ($map[$key] ?? 'digital');
    }

    /**
     * @param  array<string, mixed>  $biodataMeta
     */
    private function inferPipelineSourceFromBiodata(array $biodataMeta): string
    {
        $k = (string) ($biodataMeta['lc_lead_source'] ?? '');

        return $this->mapCaptureSourceToPipelineSource($k);
    }
}
