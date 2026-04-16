<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\AccountingRequisition;
use App\Models\AccountingSalaryAdvance;
use App\Models\Employee;
use App\Models\LoanBookApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanAccountController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function show(): View
    {
        return view('loan.account.show');
    }

    public function salaryAdvance(Request $request): View
    {
        $user = $request->user();
        $canOpenAccounting = $this->userCanOpenAccountingRoutes($user);

        $advances = collect();
        if (Schema::hasTable('accounting_salary_advances')) {
            $employeeIds = Employee::query()
                ->where('email', $user->email)
                ->pluck('id');

            if ($employeeIds->isNotEmpty()) {
                $advances = AccountingSalaryAdvance::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->with('employee')
                    ->orderByDesc('requested_on')
                    ->orderByDesc('id')
                    ->get();
            }
        }

        return view('loan.account.salary-advance', [
            'advances' => $advances,
            'canOpenAccounting' => $canOpenAccounting,
        ]);
    }

    public function approvalRequests(Request $request): View
    {
        $user = $request->user();
        $canOpenAccounting = $this->userCanOpenAccountingRoutes($user);

        $waitingOnMe = collect();

        if (Schema::hasTable('loan_book_applications')) {
            $appQuery = LoanBookApplication::query()
                ->with('loanClient:id,first_name,last_name,branch')
                ->whereIn('stage', [
                    LoanBookApplication::STAGE_CREDIT_REVIEW,
                    LoanBookApplication::STAGE_SUBMITTED,
                ]);
            $this->scopeByAssignedLoanClient($appQuery, $user);

            foreach ($appQuery->orderByDesc('updated_at')->limit(25)->get() as $app) {
                $client = $app->loanClient;
                $clientLabel = $client
                    ? trim($client->first_name.' '.$client->last_name).($client->branch ? ' · '.$client->branch : '')
                    : 'Client #'.$app->loan_client_id;

                $waitingOnMe->push([
                    'sort_at' => $app->updated_at?->timestamp ?? 0,
                    'title' => ($app->reference ?? '#'.$app->id).' · '.number_format((float) $app->amount_requested, 0).' · '.$clientLabel,
                    'meta' => 'Loan application · '.ucfirst(str_replace('_', ' ', (string) $app->stage)),
                    'when' => $app->submitted_at?->diffForHumans() ?? $app->updated_at?->diffForHumans() ?? '—',
                    'url' => route('loan.book.applications.edit', $app),
                ]);
            }
        }

        if ($canOpenAccounting) {
            if (Schema::hasTable('accounting_requisitions')) {
                foreach (AccountingRequisition::query()
                    ->where('status', AccountingRequisition::STATUS_PENDING)
                    ->orderByDesc('updated_at')
                    ->limit(20)
                    ->get() as $r) {
                    $waitingOnMe->push([
                        'sort_at' => $r->updated_at?->timestamp ?? 0,
                        'title' => $r->reference.' · '.$r->title,
                        'meta' => 'Requisition · Pending approval',
                        'when' => $r->updated_at?->diffForHumans() ?? '—',
                        'url' => route('loan.accounting.requisitions.edit', $r),
                    ]);
                }
            }

            if (Schema::hasTable('accounting_salary_advances')) {
                foreach (AccountingSalaryAdvance::query()
                    ->where('status', AccountingSalaryAdvance::STATUS_PENDING)
                    ->with('employee')
                    ->orderByDesc('updated_at')
                    ->limit(20)
                    ->get() as $a) {
                    $empName = $a->employee?->full_name ?? 'Employee #'.$a->employee_id;
                    $waitingOnMe->push([
                        'sort_at' => $a->updated_at?->timestamp ?? 0,
                        'title' => number_format((float) $a->amount, 0).' '.$a->currency.' · '.$empName,
                        'meta' => 'Salary advance · Pending approval',
                        'when' => $a->requested_on?->diffForHumans() ?? $a->updated_at?->diffForHumans() ?? '—',
                        'url' => route('loan.accounting.advances.edit', $a),
                    ]);
                }
            }
        }

        $waitingOnMe = $waitingOnMe->sortByDesc('sort_at')->values()->map(fn (array $row) => [
            'title' => $row['title'],
            'meta' => $row['meta'],
            'when' => $row['when'],
            'url' => $row['url'],
        ]);

        $submittedByMe = collect();

        if (Schema::hasTable('accounting_requisitions')) {
            foreach (AccountingRequisition::query()
                ->where('requested_by', $user->id)
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get() as $r) {
                $submittedByMe->push([
                    'sort_at' => $r->updated_at?->timestamp ?? 0,
                    'title' => $r->reference.' · '.$r->title,
                    'meta' => 'Requisition · '.ucfirst((string) $r->status),
                    'when' => 'Updated '.$r->updated_at?->diffForHumans(),
                    'url' => $canOpenAccounting ? route('loan.accounting.requisitions.edit', $r) : null,
                ]);
            }
        }

        if (Schema::hasTable('accounting_salary_advances')) {
            $employeeIds = Employee::query()->where('email', $user->email)->pluck('id');
            if ($employeeIds->isNotEmpty()) {
                foreach (AccountingSalaryAdvance::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->orderByDesc('updated_at')
                    ->limit(25)
                    ->get() as $a) {
                    $submittedByMe->push([
                        'sort_at' => $a->updated_at?->timestamp ?? 0,
                        'title' => 'Salary advance · '.number_format((float) $a->amount, 0).' '.$a->currency,
                        'meta' => 'Salary advance · '.ucfirst((string) $a->status),
                        'when' => $a->requested_on?->format('M j, Y') ?? $a->updated_at?->diffForHumans() ?? '—',
                        'url' => $canOpenAccounting ? route('loan.accounting.advances.edit', $a) : null,
                    ]);
                }
            }
        }

        $submittedByMe = $submittedByMe->sortByDesc('sort_at')->values()->map(fn (array $row) => [
            'title' => $row['title'],
            'meta' => $row['meta'],
            'when' => $row['when'],
            'url' => $row['url'],
        ]);

        return view('loan.account.approval-requests', [
            'waitingOnMe' => $waitingOnMe,
            'submittedByMe' => $submittedByMe,
            'canOpenAccounting' => $canOpenAccounting,
        ]);
    }

    private function userCanOpenAccountingRoutes(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (($user->is_super_admin ?? false) === true) {
            return true;
        }

        $role = strtolower(trim((string) ($user->loan_role ?? '')));

        return in_array($role, ['accountant', 'admin', 'manager'], true);
    }
}
