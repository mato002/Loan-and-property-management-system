<?php

namespace App\Http\Controllers\Loan\Concerns;

use App\Models\Employee;
use App\Models\LoanClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesLoanPortfolioAccess
{
    private function scopeLoanClientsToUser(Builder $query, ?User $user): void
    {
        if ($this->canAccessAllLoanData($user)) {
            return;
        }

        $employeeId = $this->resolveLoanEmployeeId($user);
        if (! $employeeId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('assigned_employee_id', $employeeId);
    }

    private function scopeByAssignedLoanClient(Builder $query, ?User $user, string $relation = 'loanClient'): void
    {
        if ($this->canAccessAllLoanData($user)) {
            return;
        }

        $employeeId = $this->resolveLoanEmployeeId($user);
        if (! $employeeId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas($relation, fn (Builder $clientQuery) => $clientQuery->where('assigned_employee_id', $employeeId));
    }

    private function ensureLoanClientAccessible(LoanClient $client, ?User $user = null): void
    {
        $user = $user ?? auth()->user();
        if ($this->canAccessAllLoanData($user)) {
            return;
        }

        abort_unless((int) $client->assigned_employee_id === (int) $this->resolveLoanEmployeeId($user), 403);
    }

    private function ensureLoanClientOwner(?LoanClient $client, ?User $user = null): void
    {
        $user = $user ?? auth()->user();
        if ($this->canAccessAllLoanData($user)) {
            return;
        }

        $employeeId = $this->resolveLoanEmployeeId($user);
        abort_unless($employeeId && $client && (int) $client->assigned_employee_id === (int) $employeeId, 403);
    }

    private function canAccessAllLoanData(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Super Admin has full cross-portfolio visibility.
        if (($user->is_super_admin ?? false) === true) {
            return true;
        }

        // Loan Admin role gets full cross-portfolio visibility.
        // Other roles (manager/officer/accountant/user) stay portfolio-scoped.
        $role = strtolower(trim((string) ($user->effectiveLoanRole() ?? '')));
        if ($role === 'admin') {
            return true;
        }

        // Everyone else is portfolio-scoped by default.
        // Optional emergency override for non-super-admin users:
        // set LOAN_GLOBAL_DATA_ACCESS=true in env.
        return (bool) env('LOAN_GLOBAL_DATA_ACCESS', false);
    }

    private function resolveLoanEmployeeId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return null;
        }

        return Employee::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->value('id');
    }
}
