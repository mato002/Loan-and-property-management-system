<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\StaffGroup;
use App\Models\StaffLeave;
use App\Models\StaffLoan;
use App\Models\StaffLoanApplication;
use App\Models\StaffPortfolio;
use App\Models\WorkplanItem;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanEmployeesController extends Controller
{
    public function index(): View
    {
        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        return view('loan.employees.index', compact('employees'));
    }

    public function create(): View
    {
        return view('loan.employees.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'string', 'max:120'],
            'hire_date' => ['nullable', 'date'],
        ]);

        Employee::create($validated);

        return redirect()
            ->route('loan.employees.index')
            ->with('status', 'Employee saved successfully.');
    }

    public function edit(Employee $employee): View
    {
        return view('loan.employees.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number,'.$employee->id],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'string', 'max:120'],
            'hire_date' => ['nullable', 'date'],
        ]);

        $employee->update($validated);

        return redirect()
            ->route('loan.employees.index')
            ->with('status', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()
            ->route('loan.employees.index')
            ->with('status', 'Employee removed.');
    }

    public function leaves(): View
    {
        $leaves = StaffLeave::query()
            ->with('employee')
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.leaves', compact('leaves', 'employees'));
    }

    public function leavesCreate(): View
    {
        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.leaves-create', compact('employees'));
    }

    public function leavesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type' => ['required', 'string', 'max:40'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;

        StaffLeave::create([
            'employee_id' => $validated['employee_id'],
            'leave_type' => $validated['leave_type'],
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('loan.employees.leaves')
            ->with('status', 'Leave request submitted.');
    }

    public function leavesUpdateStatus(Request $request, StaffLeave $staff_leave): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $staff_leave->update(['status' => $validated['status']]);

        return back()->with('status', 'Leave status updated.');
    }

    public function groups(): View
    {
        $groups = StaffGroup::query()
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return view('loan.employees.groups', compact('groups'));
    }

    public function groupsCreate(): View
    {
        return view('loan.employees.groups-create');
    }

    public function groupsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $staff_group = StaffGroup::create($validated);

        return redirect()
            ->route('loan.employees.groups.show', $staff_group)
            ->with('status', 'Group created. Add members below.');
    }

    public function groupsShow(StaffGroup $staff_group): View
    {
        $staff_group->load(['employees' => fn ($q) => $q->orderBy('last_name')->orderBy('first_name')]);

        $availableEmployees = Employee::query()
            ->whereNotIn('id', $staff_group->employees->pluck('id'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.groups-show', compact('staff_group', 'availableEmployees'));
    }

    public function groupsMemberStore(Request $request, StaffGroup $staff_group): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        $staff_group->employees()->syncWithoutDetaching([$validated['employee_id']]);

        return back()->with('status', 'Member added to group.');
    }

    public function groupsMemberDestroy(StaffGroup $staff_group, Employee $employee): RedirectResponse
    {
        $staff_group->employees()->detach($employee->id);

        return back()->with('status', 'Member removed from group.');
    }

    public function groupsDestroy(StaffGroup $staff_group): RedirectResponse
    {
        $staff_group->delete();

        return redirect()
            ->route('loan.employees.groups')
            ->with('status', 'Group deleted.');
    }

    public function portfolios(): View
    {
        $portfolios = StaffPortfolio::query()
            ->with('employee')
            ->orderBy('portfolio_code')
            ->paginate(20);

        return view('loan.employees.portfolios', compact('portfolios'));
    }

    public function portfoliosCreate(): View
    {
        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.portfolios-create', compact('employees'));
    }

    public function portfoliosStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'portfolio_code' => ['required', 'string', 'max:80', 'unique:staff_portfolios,portfolio_code'],
            'active_loans' => ['nullable', 'integer', 'min:0'],
            'outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'par_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        StaffPortfolio::create([
            'employee_id' => $validated['employee_id'],
            'portfolio_code' => $validated['portfolio_code'],
            'active_loans' => $validated['active_loans'] ?? 0,
            'outstanding_amount' => $validated['outstanding_amount'] ?? null,
            'par_rate' => $validated['par_rate'] ?? null,
        ]);

        return redirect()
            ->route('loan.employees.portfolios')
            ->with('status', 'Portfolio assignment saved.');
    }

    public function portfoliosEdit(StaffPortfolio $staff_portfolio): View
    {
        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.portfolios-edit', [
            'portfolio' => $staff_portfolio,
            'employees' => $employees,
        ]);
    }

    public function portfoliosUpdate(Request $request, StaffPortfolio $staff_portfolio): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'portfolio_code' => ['required', 'string', 'max:80', 'unique:staff_portfolios,portfolio_code,'.$staff_portfolio->id],
            'active_loans' => ['nullable', 'integer', 'min:0'],
            'outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'par_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $staff_portfolio->update([
            'employee_id' => $validated['employee_id'],
            'portfolio_code' => $validated['portfolio_code'],
            'active_loans' => $validated['active_loans'] ?? 0,
            'outstanding_amount' => $validated['outstanding_amount'] ?? null,
            'par_rate' => $validated['par_rate'] ?? null,
        ]);

        return redirect()
            ->route('loan.employees.portfolios')
            ->with('status', 'Portfolio updated.');
    }

    public function portfoliosDestroy(StaffPortfolio $staff_portfolio): RedirectResponse
    {
        $staff_portfolio->delete();

        return redirect()
            ->route('loan.employees.portfolios')
            ->with('status', 'Portfolio removed.');
    }

    public function loanApplications(): View
    {
        $applications = StaffLoanApplication::query()
            ->with('employee')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('loan.employees.loan-applications', compact('applications'));
    }

    public function loanApplicationsCreate(): View
    {
        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.loan-applications-create', compact('employees'));
    }

    public function loanApplicationsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'product' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'stage' => ['nullable', 'string', 'max:120'],
        ]);

        $application = StaffLoanApplication::create([
            'employee_id' => $validated['employee_id'],
            'product' => $validated['product'],
            'amount' => $validated['amount'],
            'stage' => $validated['stage'] ?? 'Submitted',
            'status' => 'pending',
        ]);

        $application->update([
            'reference' => 'SLA-'.str_pad((string) $application->id, 5, '0', STR_PAD_LEFT),
        ]);

        return redirect()
            ->route('loan.employees.loan_applications')
            ->with('status', 'Staff loan application created.');
    }

    public function loanApplicationsUpdate(Request $request, StaffLoanApplication $staff_loan_application): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:pending,approved,declined,disbursed'],
        ]);

        $staff_loan_application->update($validated);

        return back()->with('status', 'Application updated.');
    }

    public function staffLoans(): View
    {
        $loans = StaffLoan::query()
            ->with('employee')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('loan.employees.staff-loans', compact('loans'));
    }

    public function staffLoansCreate(): View
    {
        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.staff-loans-create', compact('employees'));
    }

    public function staffLoansStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'principal' => ['required', 'numeric', 'min:0'],
            'balance' => ['required', 'numeric', 'min:0'],
            'next_due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:current,arrears,closed'],
        ]);

        $loan = StaffLoan::create([
            'employee_id' => $validated['employee_id'],
            'principal' => $validated['principal'],
            'balance' => $validated['balance'],
            'next_due_date' => $validated['next_due_date'] ?? null,
            'status' => $validated['status'],
        ]);

        $loan->update([
            'account_ref' => 'STF-'.str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT),
        ]);

        return redirect()
            ->route('loan.employees.staff_loans')
            ->with('status', 'Staff loan record created.');
    }

    public function staffLoansEdit(StaffLoan $staff_loan): View
    {
        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.employees.staff-loans-edit', [
            'loan' => $staff_loan,
            'employees' => $employees,
        ]);
    }

    public function staffLoansUpdate(Request $request, StaffLoan $staff_loan): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'principal' => ['required', 'numeric', 'min:0'],
            'balance' => ['required', 'numeric', 'min:0'],
            'next_due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:current,arrears,closed'],
        ]);

        $staff_loan->update($validated);

        return redirect()
            ->route('loan.employees.staff_loans')
            ->with('status', 'Staff loan updated.');
    }

    public function staffLoansDestroy(StaffLoan $staff_loan): RedirectResponse
    {
        $staff_loan->delete();

        return redirect()
            ->route('loan.employees.staff_loans')
            ->with('status', 'Staff loan record removed.');
    }

    public function workplan(Request $request): View
    {
        $today = $request->date('date') ?: now()->toDateString();
        $todayCarbon = Carbon::parse($today);
        $tomorrow = $todayCarbon->copy()->addDay()->toDateString();

        $userId = $request->user()->id;

        $todayItems = WorkplanItem::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $today)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $tomorrowItems = WorkplanItem::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $tomorrow)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stats = [
            'total' => $todayItems->count(),
            'done' => $todayItems->where('is_done', true)->count(),
        ];

        return view('loan.employees.workplan', compact('todayItems', 'tomorrowItems', 'stats', 'today', 'tomorrow'));
    }

    public function workplanItemStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'work_date' => ['required', 'date'],
        ]);

        $maxOrder = (int) WorkplanItem::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('work_date', $validated['work_date'])
            ->max('sort_order');

        WorkplanItem::create([
            'user_id' => $request->user()->id,
            'work_date' => $validated['work_date'],
            'title' => $validated['title'],
            'is_done' => false,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('status', 'Task added.');
    }

    public function workplanItemToggle(WorkplanItem $workplan_item): RedirectResponse
    {
        $this->assertWorkplanOwner($workplan_item);

        $workplan_item->update(['is_done' => ! $workplan_item->is_done]);

        return back();
    }

    public function workplanItemDestroy(WorkplanItem $workplan_item): RedirectResponse
    {
        $this->assertWorkplanOwner($workplan_item);

        $workplan_item->delete();

        return back()->with('status', 'Task removed.');
    }

    private function assertWorkplanOwner(WorkplanItem $item): void
    {
        abort_unless($item->user_id === auth()->id(), 403);
    }
}
