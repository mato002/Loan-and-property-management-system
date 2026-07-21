<?php

namespace App\Console\Commands;

use App\Services\Property\RentReminderEligibilityService;
use App\Services\Property\SmsCommunicationAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CommunicationsSmsAuditCommand extends Command
{
    protected $signature = 'communications:sms-audit
                            {--from= : Limit audit to logs on/after this date (Y-m-d)}
                            {--to= : Limit audit to logs on/before this date (Y-m-d)}
                            {--fix-supersede : Mark stale failed rows as superseded when a sent row exists}
                            {--dry-run : With --fix-supersede, report only}';

    protected $description = 'Audit SMS logs: unresolved failures, duplicate sends, duplicate charges, and retry storms';

    public function handle(
        SmsCommunicationAuditService $audit,
        RentReminderEligibilityService $eligibility,
    ): int {
        $from = $this->option('from') ? Carbon::parse((string) $this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to'))->startOfDay() : null;

        $report = $audit->runAudit($from, $to);

        $this->info('=== SMS communications audit ===');
        if ($from || $to) {
            $this->line('Date range: '.($from?->toDateString() ?? '…').' → '.($to?->toDateString() ?? '…'));
        }

        $this->newLine();
        $this->info('Unresolved failed (needs action): '.$report['unresolved_failed']['count']);
        foreach ($report['unresolved_failed']['samples'] as $row) {
            $this->line(sprintf(
                '  #%d %s | %s | stage %s | %s',
                $row['id'],
                $row['to'],
                $row['subject'],
                $row['stage'] !== '' ? $row['stage'] : '—',
                $row['created_at'] ?? ''
            ));
        }

        $this->newLine();
        $this->info('Duplicate sent groups: '.$report['duplicate_sent']['count']);
        foreach ($report['duplicate_sent']['groups'] as $group) {
            $this->line(sprintf(
                '  %s | %s | stage %s | %s | rows=%d',
                $group['to_address'],
                $group['invoice'] ?? $group['subject'] ?? '—',
                $group['stage'] !== '' ? $group['stage'] : '—',
                $group['day'],
                $group['row_count']
            ));
        }

        $this->newLine();
        $this->info('Duplicate charge candidates (sms_logs): '.$report['duplicate_charge_candidates']['count']);
        foreach ($report['duplicate_charge_candidates']['groups'] as $group) {
            $this->line(sprintf(
                '  %s | %s | charged=%s | sends=%d',
                $group['phone'],
                $group['day'],
                number_format($group['total_charged'], 2),
                $group['send_count']
            ));
        }

        $this->newLine();
        $this->info('Retry storms (3+ failed same phone/subject/day): '.$report['retry_storms']['count']);
        foreach ($report['retry_storms']['groups'] as $group) {
            $this->line(sprintf(
                '  %s | %s | %s | failed=%d',
                $group['to_address'],
                $group['subject'],
                $group['day'],
                $group['failed_count']
            ));
        }

        if ($this->option('fix-supersede')) {
            $this->newLine();
            $dryRun = (bool) $this->option('dry-run');
            $result = $eligibility->supersedeStaleFailedSmsLogs($from, $to, $dryRun);
            $verb = $dryRun ? 'Would supersede' : 'Superseded';
            $this->warn("{$verb} {$result['superseded']} stale failed row(s) (scanned {$result['scanned']}, skipped {$result['skipped']}).");
        }

        return self::SUCCESS;
    }
}
