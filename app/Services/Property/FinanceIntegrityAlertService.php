<?php

namespace App\Services\Property;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class FinanceIntegrityAlertService
{
    public function notifyIfCritical(array $report, string $scope): void
    {
        $critical = (int) ($report['summary']['critical'] ?? 0);
        if ($critical <= 0) {
            return;
        }

        $cooldown = (int) config('finance-integrity.alert_cooldown_minutes', 60);
        $cacheKey = 'finance_integrity_alert:'.md5($scope.':'.now()->format('Y-m-d-H'));
        if (! Cache::add($cacheKey, true, now()->addMinutes($cooldown))) {
            return;
        }

        $message = $this->buildMessage($report, $scope);

        if (config('finance-integrity.alert_slack') && env('LOG_SLACK_WEBHOOK_URL')) {
            try {
                Log::channel('slack')->critical($message);
            } catch (\Throwable $e) {
                Log::warning('Finance integrity Slack alert failed: '.$e->getMessage());
            }
        }

        $email = trim((string) config('finance-integrity.alert_email', ''));
        if ($email !== '') {
            try {
                Mail::raw($message, function ($mail) use ($email, $scope) {
                    $mail->to($email)
                        ->subject('[CRITICAL] Finance integrity drift — '.$scope);
                });
            } catch (\Throwable $e) {
                Log::warning('Finance integrity email alert failed: '.$e->getMessage());
            }
        }
    }

    private function buildMessage(array $report, string $scope): string
    {
        $summary = $report['summary'] ?? [];
        $lines = [
            'CRITICAL finance integrity drift detected',
            'Scope: '.$scope,
            'Run at: '.($report['run_at'] ?? now()->toIso8601String()),
            sprintf(
                'Issues: %d total — critical: %d, warning: %d, info: %d',
                (int) ($summary['total_issues'] ?? 0),
                (int) ($summary['critical'] ?? 0),
                (int) ($summary['warning'] ?? 0),
                (int) ($summary['info'] ?? 0),
            ),
            'Affected tenants: '.(int) ($summary['affected_tenants'] ?? 0),
            'Affected invoices: '.(int) ($summary['affected_invoices'] ?? 0),
            '',
            'Open the Finance Integrity dashboard in the property agent portal.',
        ];

        foreach ($report['categories'] ?? [] as $key => $category) {
            $catCritical = (int) (($category['summary'] ?? [])['critical'] ?? 0);
            if ($catCritical <= 0) {
                continue;
            }
            $lines[] = sprintf(
                '- %s: %d critical (%d total)',
                (string) ($category['label'] ?? $key),
                $catCritical,
                (int) (($category['summary'] ?? [])['count'] ?? 0),
            );
        }

        return implode("\n", $lines);
    }
}
