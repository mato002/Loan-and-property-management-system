<?php

namespace App\Http\Middleware;

use App\Models\LoanAccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogLoanPortalAccess
{
    private static ?bool $hasActivityColumn = null;

    private static ?bool $hasForensicsColumns = null;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_starts_with($request->path(), 'loan/')) {
            return $response;
        }

        try {
            $userId = $request->user()?->id;
            $payload = [
                'user_id' => $userId,
                'session_id' => $request->session()->getId(),
                'device_fingerprint' => substr(hash('sha256', (string) $request->userAgent()), 0, 40),
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'ip_address' => $request->ip(),
                'country_code' => $this->detectCountryCode($request->ip()),
                'geo_label' => $this->geoLabel($request->ip()),
                'is_foreign_ip' => $this->isForeignIp($request->ip()),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ];

            if ($this->supportsActivityColumn()) {
                $payload['activity'] = $this->buildActivitySummary($request, $response);
            }
            if ($this->supportsForensicsColumns()) {
                $risk = $this->calculateRisk($request, $response, $payload['activity'] ?? null);
                $payload = array_merge($payload, $risk, [
                    'audit_token' => $this->makeAuditToken((int) ($userId ?? 0)),
                    'previous_hash' => LoanAccessLog::query()->latest('id')->value('checksum'),
                    'mfa_verified' => null,
                ]);
                $payload['checksum'] = $this->buildChecksum($payload);
            }

            LoanAccessLog::query()->create($payload);
        } catch (\Throwable) {
            // Never block the portal if logging fails (e.g. migration not run yet).
        }

        return $response;
    }

    private function supportsActivityColumn(): bool
    {
        if (self::$hasActivityColumn !== null) {
            return self::$hasActivityColumn;
        }

        try {
            self::$hasActivityColumn = Schema::hasColumn('loan_access_logs', 'activity');
        } catch (\Throwable) {
            self::$hasActivityColumn = false;
        }

        return self::$hasActivityColumn;
    }

    private function supportsForensicsColumns(): bool
    {
        if (self::$hasForensicsColumns !== null) {
            return self::$hasForensicsColumns;
        }

        try {
            self::$hasForensicsColumns = Schema::hasColumn('loan_access_logs', 'risk_score')
                && Schema::hasColumn('loan_access_logs', 'checksum')
                && Schema::hasColumn('loan_access_logs', 'audit_token');
        } catch (\Throwable) {
            self::$hasForensicsColumns = false;
        }

        return self::$hasForensicsColumns;
    }

    private function buildActivitySummary(Request $request, Response $response): string
    {
        $method = strtoupper((string) $request->method());
        $resource = $this->friendlyResourceName($request);
        $statusCode = $response->getStatusCode();
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName !== '') {
            $specific = $this->specificActivityFromRoute($routeName, $method);
            if ($specific !== null) {
                if ($statusCode >= 400) {
                    return "{$specific} failed ({$statusCode})";
                }

                return $specific;
            }
        }

        if ($method === 'GET') {
            return $statusCode >= 400
                ? "Failed to access {$resource} ({$statusCode})"
                : "Accessed {$resource}";
        }

        $verb = match ($method) {
            'POST' => 'Created/Submitted',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Performed action on',
        };

        if ($statusCode >= 400) {
            return "{$verb} {$resource} failed ({$statusCode})";
        }

        return "{$verb} {$resource}";
    }

    private function specificActivityFromRoute(string $routeName, string $method): ?string
    {
        $routeActivity = [
            'loan.dashboard' => 'Viewed operations dashboard',
            'loan.clients.index' => 'Viewed clients list',
            'loan.clients.leads' => 'Viewed client leads',
            'loan.clients.leads.show' => 'Viewed lead workspace',
            'loan.book.loans.index' => 'Viewed loans register',
            'loan.book.applications.index' => 'Viewed loan applications',
            'loan.book.disbursements.index' => 'Viewed disbursements',
            'loan.payments.unposted' => 'Viewed unposted payments',
            'loan.payments.processed' => 'Viewed processed payments',
            'loan.payments.report' => 'Viewed payments report',
            'loan.employees.index' => 'Viewed employees list',
            'loan.employees.leaves' => 'Viewed staff leaves',
            'loan.employees.groups' => 'Viewed staff groups',
            'loan.accounting.books' => 'Viewed books of account',
            'loan.system.access_logs.index' => 'Viewed system access logs',
            'loan.system.tickets.index' => 'Viewed support tickets',
            'loan.system.tickets.create' => 'Opened create ticket form',
            'loan.system.setup' => 'Viewed system setup',
            'loan.system.setup.loan_products' => 'Viewed loan products setup',
            'loan.notifications.index' => 'Viewed notifications',
        ];

        if (isset($routeActivity[$routeName]) && $method === 'GET') {
            return $routeActivity[$routeName];
        }

        if (Str::endsWith($routeName, '.store') && $method === 'POST') {
            return 'Submitted '.Str::headline(str_replace(['loan.', '.store', '.'], ['', '', ' '], $routeName));
        }
        if (Str::endsWith($routeName, '.update') && in_array($method, ['PATCH', 'PUT'], true)) {
            return 'Updated '.Str::headline(str_replace(['loan.', '.update', '.'], ['', '', ' '], $routeName));
        }
        if (Str::endsWith($routeName, '.destroy') && $method === 'DELETE') {
            return 'Deleted '.Str::headline(str_replace(['loan.', '.destroy', '.'], ['', '', ' '], $routeName));
        }

        return null;
    }

    private function friendlyResourceName(Request $request): string
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName !== '') {
            $clean = str_replace(['loan.', '.'], ['', ' '], $routeName);
            $clean = preg_replace('/\s+/', ' ', $clean) ?: $clean;

            return Str::headline(trim($clean));
        }

        $segments = array_values(array_filter(explode('/', trim((string) $request->path(), '/'))));
        if ($segments !== [] && $segments[0] === 'loan') {
            array_shift($segments);
        }

        return $segments !== []
            ? Str::headline(implode(' ', $segments))
            : 'Loan portal';
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateRisk(Request $request, Response $response, ?string $activity): array
    {
        $method = strtoupper((string) $request->method());
        $route = (string) ($request->route()?->getName() ?? '');
        $path = '/'.$request->path();
        $statusCode = $response->getStatusCode();

        $score = 10;
        $reasons = [];
        $actionType = $this->detectActionType($method, $route, $path);

        if ($actionType !== 'view') {
            $score += 15;
        }

        if ($statusCode >= 400) {
            $score += 35;
            $reasons[] = 'Request failed';
        }

        $sensitivePatterns = ['accounting', 'journal', 'reversal', 'income_statement', 'balance_sheet', 'export', 'clients.update'];
        if ($route !== '' && collect($sensitivePatterns)->contains(fn (string $p) => Str::contains($route, $p))) {
            $score += 25;
            $reasons[] = 'Sensitive route access';
        }

        if ($this->isForeignIp($request->ip())) {
            $score += 35;
            $reasons[] = 'Foreign IP';
        }

        if ($activity && Str::contains(Str::lower($activity), ['failed', 'blocked', 'reversal', 'override'])) {
            $score += 20;
            $reasons[] = 'High-risk activity keyword';
        }

        $score = min(100, $score);

        $riskLevel = match (true) {
            $score >= 90 => 'critical',
            $score >= 70 => 'high',
            $score >= 40 => 'medium',
            default => 'low',
        };

        return [
            'event_category' => $this->eventCategoryFromRoute($route),
            'action_type' => $actionType,
            'result' => $statusCode >= 400 ? 'blocked' : 'success',
            'risk_score' => $score,
            'risk_level' => $riskLevel,
            'risk_reason' => implode('; ', $reasons) ?: 'Routine activity',
            'requires_reason' => $this->requiresReason($route),
            'reason_text' => null,
            'is_privileged' => $this->isPrivilegedAction($route),
        ];
    }

    private function detectActionType(string $method, string $route, string $path): string
    {
        $needle = Str::lower($route.' '.$path);

        if (Str::contains($needle, ['import'])) {
            return 'import';
        }
        if (Str::contains($needle, ['export'])) {
            return 'export';
        }
        if (Str::contains($needle, ['download', 'template'])) {
            return 'download';
        }

        if ($method === 'GET') {
            return 'view';
        }

        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'action',
        };
    }

    private function eventCategoryFromRoute(string $routeName): string
    {
        if (Str::contains($routeName, 'accounting')) {
            return 'accounting';
        }
        if (Str::contains($routeName, 'payments') || Str::contains($routeName, 'book')) {
            return 'loan';
        }
        if (Str::contains($routeName, 'system')) {
            return 'system';
        }
        if (Str::contains($routeName, 'dashboard') || Str::contains($routeName, 'report')) {
            return 'report';
        }

        return 'access';
    }

    private function requiresReason(string $routeName): bool
    {
        return Str::contains($routeName, [
            'chart',
            'journal.reverse',
            'clients.update',
            'reports.income_statement',
            'reports.balance_sheet',
        ]);
    }

    private function isPrivilegedAction(string $routeName): bool
    {
        return Str::contains($routeName, [
            'accounting',
            'setup.access_roles',
            'journal.reverse',
            'requisitions.approve',
            'advances.approve',
        ]);
    }

    private function makeAuditToken(int $userId): string
    {
        return strtoupper('AUD'.dechex((int) now()->timestamp).'-'.$userId.'-'.Str::upper(Str::random(4)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildChecksum(array $payload): string
    {
        $hashSource = implode('|', [
            $payload['user_id'] ?? '',
            $payload['session_id'] ?? '',
            $payload['route_name'] ?? '',
            $payload['method'] ?? '',
            $payload['path'] ?? '',
            $payload['activity'] ?? '',
            $payload['result'] ?? '',
            $payload['risk_score'] ?? '',
            $payload['previous_hash'] ?? '',
            (string) now()->toIso8601String(),
        ]);

        return hash('sha256', $hashSource);
    }

    private function detectCountryCode(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'KE';
        }
        if (Str::startsWith($ip, ['192.168.', '10.', '172.16.'])) {
            return 'KE';
        }

        return 'UNK';
    }

    private function geoLabel(?string $ip): ?string
    {
        $country = $this->detectCountryCode($ip);
        if ($country === null) {
            return null;
        }

        return match ($country) {
            'KE' => 'Nairobi, KE',
            default => 'Unknown',
        };
    }

    private function isForeignIp(?string $ip): bool
    {
        return $this->detectCountryCode($ip) !== 'KE';
    }
}
