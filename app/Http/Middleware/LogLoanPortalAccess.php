<?php

namespace App\Http\Middleware;

use App\Models\LoanAccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LogLoanPortalAccess
{
    private static ?bool $hasActivityColumn = null;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || ! str_starts_with($request->path(), 'loan/')) {
            return $response;
        }

        try {
            $payload = [
                'user_id' => $request->user()->id,
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ];

            if ($this->supportsActivityColumn()) {
                $payload['activity'] = $this->buildActivitySummary($request, $response);
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
}
