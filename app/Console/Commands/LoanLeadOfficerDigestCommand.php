<?php

namespace App\Console\Commands;

use App\Models\ClientLead;
use App\Models\ClientLeadActivity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LoanLeadOfficerDigestCommand extends Command
{
    protected $signature = 'loan:lead-officer-digest {--date=}';

    protected $description = 'Emit a daily per-officer lead pipeline digest to the application log (extend to mail as needed).';

    public function handle(): int
    {
        $day = $this->option('date') ? (string) $this->option('date') : now()->toDateString();

        $officerIds = ClientLead::query()
            ->whereDate('created_at', '<=', $day)
            ->whereNotNull('assigned_officer_id')
            ->distinct()
            ->pluck('assigned_officer_id');

        foreach ($officerIds as $uid) {
            $user = User::query()->find($uid);
            if (! $user) {
                continue;
            }

            $base = ClientLead::query()->where('assigned_officer_id', $uid);
            $mtd = (clone $base)->where('created_at', '>=', now()->startOfMonth())->count();
            $open = (clone $base)->where('pipeline_status', 'active')->count();
            $due = ClientLeadActivity::query()
                ->whereIn('client_lead_id', (clone $base)->pluck('id'))
                ->whereDate('next_action_date', '<=', $day)
                ->count();

            $line = sprintf(
                '[lead-digest] user=%s mtd=%d open_active=%d due_followups=%s day=%s',
                (string) ($user->email ?? $user->id),
                $mtd,
                $open,
                (string) $due,
                $day
            );

            Log::info($line);
            $this->line($line);
        }

        $this->info('Digest complete.');

        return self::SUCCESS;
    }
}
