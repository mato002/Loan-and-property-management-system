<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LoanBranch;
use App\Models\LoanDepartment;
use App\Models\LoanJobTitle;
use App\Models\LoanRole;
use App\Models\LoanRegion;
use App\Models\StaffGroup;
use App\Models\StaffLeave;
use App\Models\StaffLoan;
use App\Models\StaffLoanApplication;
use App\Models\StaffPortfolio;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Notifications\Loan\LoanWorkflowNotification;
use App\Models\WorkplanItem;
use App\Mail\LoanEmployeeCredentialsMail;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class LoanEmployeesController extends Controller
{
    /**
     * @return array<string, string>
     */
    private static function roleDepartmentMap(): array
    {
        return [
            'admin' => 'Operations',
            'manager' => 'Operations',
            'officer' => 'Credit',
            'accountant' => 'Finance',
            'applicant' => 'Customer Service',
            'user' => 'Operations',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function roleJobTitleMap(): array
    {
        return [
            'admin' => 'Loan administrator',
            'manager' => 'Loan manager',
            'officer' => 'Loan officer',
            'accountant' => 'Accountant',
            'applicant' => 'Customer care officer',
            'user' => 'Loan staff',
        ];
    }

    /**
     * @return list<string>
     */
    private static function loanRoleOptions(): array
    {
        return ['admin', 'officer', 'manager', 'applicant', 'accountant', 'user'];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{slug: string, name: string, base_role: string}>
     */
    private function loanRoleChoices()
    {
        if (! Schema::hasTable('loan_roles')) {
            return collect(self::loanRoleOptions())->map(fn (string $role) => [
                'slug' => $role,
                'name' => ucfirst($role),
                'base_role' => $role,
            ]);
        }

        $rows = LoanRole::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['slug', 'name', 'base_role']);

        if ($rows->isNotEmpty()) {
            return $rows->map(fn (LoanRole $r) => [
                'slug' => (string) $r->slug,
                'name' => (string) $r->name,
                'base_role' => (string) $r->base_role,
            ]);
        }

        return collect(self::loanRoleOptions())->map(fn (string $role) => [
            'slug' => $role,
            'name' => ucfirst($role),
            'base_role' => $role,
        ]);
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $department = trim((string) $request->query('department', ''));
        $branch = trim((string) $request->query('branch', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 15)));

        $employeesQuery = Employee::query()
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('employee_number', 'like', '%'.$q.'%')
                        ->orWhere('first_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('job_title', 'like', '%'.$q.'%');
                });
            })
            ->when($department !== '', fn ($builder) => $builder->where('department', $department))
            ->when($branch !== '', fn ($builder) => $builder->where('branch', $branch))
            ->orderBy('last_name')
            ->orderBy('first_name');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $employeesQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loan-employees-'.now()->format('Ymd_His'),
                ['Employee #', 'Name', 'Email', 'Phone', 'Department', 'Job title', 'Branch', 'Hire date'],
                function () use ($rows) {
                    foreach ($rows as $employee) {
                        yield [
                            (string) $employee->employee_number,
                            (string) $employee->full_name,
                            (string) ($employee->email ?? ''),
                            (string) ($employee->phone ?? ''),
                            (string) ($employee->department ?? ''),
                            (string) ($employee->job_title ?? ''),
                            (string) ($employee->branch ?? ''),
                            optional($employee->hire_date)->format('Y-m-d') ?? '',
                        ];
                    }
                },
                $export
            );
        }

        $employees = $employeesQuery
            ->paginate($perPage)
            ->withQueryString();

        $departments = Employee::query()->whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department');
        $branches = Employee::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch');

        return view('loan.employees.index', compact('employees', 'q', 'department', 'branch', 'perPage', 'departments', 'branches'));
    }

    public function create(): View
    {
        $roleDepartmentMap = self::roleDepartmentMap();
        $roleJobTitleMap = self::roleJobTitleMap();

        $departmentNames = $this->departmentOptions();
        $jobTitleOptions = $this->jobTitleOptions();

        $branches = Schema::hasTable('loan_branches')
            ? LoanBranch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'loan_region_id'])
            : collect();

        $regions = Schema::hasTable('loan_regions')
            ? LoanRegion::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('loan.employees.create', [
            'loanRoleOptions' => $this->loanRoleChoices(),
            'suggestedEmployeeNumber' => $this->generateNextEmployeeNumber(),
            'roleDepartmentMap' => $roleDepartmentMap,
            'roleJobTitleMap' => $roleJobTitleMap,
            'departmentNames' => $departmentNames,
            'jobTitleOptions' => $jobTitleOptions,
            'branches' => $branches,
            'regions' => $regions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $roleChoices = $this->loanRoleChoices();
        $allowedRoleSlugs = $roleChoices->pluck('slug')->all();
        $baseRoleBySlug = $roleChoices->pluck('base_role', 'slug')->all();

        $validated = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:employees,employee_number'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'string', 'max:120'],
            'hire_date' => ['nullable', 'date'],
            'loan_role' => ['nullable', 'string', 'in:'.implode(',', $allowedRoleSlugs)],
        ]);

        $provisionLogin = filled($validated['loan_role'] ?? null);
        $normalizedEmail = Str::lower(trim((string) ($validated['email'] ?? '')));

        if ($provisionLogin && $normalizedEmail === '') {
            return back()
                ->withErrors(['email' => 'Work email is required to send login credentials for the selected role.'])
                ->withInput();
        }

        if ($provisionLogin && User::query()->where('email', $normalizedEmail)->exists()) {
            return back()
                ->withErrors(['email' => 'A user account with this email already exists. Use a different email or edit the existing user role.'])
                ->withInput();
        }

        $employeeData = $validated;
        unset($employeeData['loan_role']);
        $employeeData['employee_number'] = trim((string) ($employeeData['employee_number'] ?? ''));
        if ($employeeData['employee_number'] === '') {
            $employeeData['employee_number'] = $this->generateNextEmployeeNumber();
        }

        $provisionedUser = null;
        $plainPassword = null;

        DB::transaction(function () use ($employeeData, $provisionLogin, $normalizedEmail, $validated, $baseRoleBySlug, &$provisionedUser, &$plainPassword): void {
            Employee::create($employeeData);

            if (! $provisionLogin) {
                return;
            }

            $plainPassword = $this->generateReadableTempPassword();

            $selectedRoleSlug = (string) ($validated['loan_role'] ?? '');
            $selectedBaseRole = (string) ($baseRoleBySlug[$selectedRoleSlug] ?? 'user');

            $provisionedUser = User::query()->create([
                'name' => trim(($employeeData['first_name'] ?? '').' '.($employeeData['last_name'] ?? '')),
                'email' => $normalizedEmail,
                'password' => Hash::make($plainPassword),
                'loan_role' => $selectedBaseRole,
                'property_portal_role' => null,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            if (Schema::hasTable('loan_roles') && Schema::hasTable('loan_user_role')) {
                $roleId = LoanRole::query()
                    ->where('slug', $selectedRoleSlug)
                    ->where('is_active', true)
                    ->value('id');
                if ($roleId) {
                    DB::table('loan_user_role')->where('user_id', $provisionedUser->id)->delete();
                    DB::table('loan_user_role')->insert([
                        'loan_role_id' => (int) $roleId,
                        'user_id' => (int) $provisionedUser->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (Schema::hasTable('user_module_accesses')) {
                UserModuleAccess::query()->updateOrCreate(
                    ['user_id' => $provisionedUser->id, 'module' => 'loan'],
                    [
                        'status' => UserModuleAccess::STATUS_APPROVED,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]
                );
            }
        });

        if ($provisionedUser !== null && $plainPassword !== null) {
            try {
                Mail::to($provisionedUser->email)->send(new LoanEmployeeCredentialsMail(
                    employeeName: $provisionedUser->name,
                    role: (string) ($validated['loan_role'] ?? $provisionedUser->loan_role ?? 'user'),
                    email: $provisionedUser->email,
                    plainPassword: $plainPassword,
                    loginUrl: route('login'),
                    loanHomeUrl: route('loan.dashboard'),
                ));

                $request->user()?->notify(new LoanWorkflowNotification(
                    'Employee created',
                    'Employee '.$provisionedUser->name.' was created and login credentials were sent.',
                    route('loan.employees.index')
                ));

                return redirect()
                    ->route('loan.employees.index')
                    ->with('status', 'Employee saved and login credentials emailed.');
            } catch (Throwable $e) {
                Log::error('loan_employee_credentials_mail_failed', [
                    'message' => $e->getMessage(),
                    'user_id' => $provisionedUser->id,
                    'email' => $provisionedUser->email,
                ]);

                $request->user()?->notify(new LoanWorkflowNotification(
                    'Employee created',
                    'Employee '.$provisionedUser->name.' was created, but credential email failed.',
                    route('loan.employees.index')
                ));

                return redirect()
                    ->route('loan.employees.index')
                    ->with('status', 'Employee and login saved, but credential email failed. Share login email and reset password manually.');
            }
        }

        $request->user()?->notify(new LoanWorkflowNotification(
            'Employee created',
            'New employee profile was created successfully.',
            route('loan.employees.index')
        ));

        return redirect()
            ->route('loan.employees.index')
            ->with('status', 'Employee saved successfully.');
    }

    public function departmentsStore(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('loan_departments')) {
            return back()->with('error', 'Loan departments table is not available. Run migrations.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
        ]);

        LoanDepartment::query()->updateOrCreate(
            ['name' => trim($validated['name'])],
            [
                'code' => filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null,
                'is_active' => true,
            ]
        );

        return back()->with('status', 'Department added.');
    }

    public function branchesStore(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('loan_branches') || ! Schema::hasTable('loan_regions')) {
            return back()->with('error', 'Loan branches/regions tables are not available. Run migrations.');
        }

        $validated = $request->validate([
            'loan_region_id' => ['required', 'exists:loan_regions,id'],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        LoanBranch::query()->create([
            'loan_region_id' => (int) $validated['loan_region_id'],
            'name' => trim($validated['name']),
            'code' => filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null,
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'address' => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
            'is_active' => true,
        ]);

        return back()->with('status', 'Branch added.');
    }

    public function edit(Employee $employee): View
    {
        return view('loan.employees.edit', [
            'employee' => $employee,
            'departmentNames' => $this->departmentOptions(),
            'jobTitleOptions' => $this->jobTitleOptions(),
        ]);
    }

    public function show(Employee $employee): View
    {
        $linkedUser = null;
        $email = Str::lower(trim((string) ($employee->email ?? '')));
        if ($email !== '') {
            $linkedUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        return view('loan.employees.show', [
            'employee' => $employee,
            'linkedUser' => $linkedUser,
        ]);
    }

    public function resendLoginCredentials(Employee $employee): RedirectResponse
    {
        $email = Str::lower(trim((string) ($employee->email ?? '')));
        if ($email === '') {
            return back()->with('error', 'Employee has no work email. Add one before resending credentials.');
        }
        if (! $this->isLikelyDeliverableEmail($email)) {
            return back()->with('error', 'Email domain is not deliverable from this server. Use a real mailbox domain (not .local) and try again.');
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return back()->with('error', 'No linked user account found for this employee email.');
        }

        $plainPassword = $this->generateReadableTempPassword();
        $user->update([
            'password' => Hash::make($plainPassword),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        try {
            Mail::to($user->email)->send(new LoanEmployeeCredentialsMail(
                employeeName: (string) ($user->name ?: $employee->full_name),
                role: (string) ($user->effectiveLoanRole() ?: $user->loan_role ?: 'user'),
                email: $user->email,
                plainPassword: $plainPassword,
                loginUrl: route('login'),
                loanHomeUrl: route('loan.dashboard'),
            ));

            return back()->with('status', 'Login credentials dispatched to SMTP server. Ask user to check Inbox/Spam.');
        } catch (Throwable $e) {
            Log::error('loan_employee_credentials_resend_failed', [
                'message' => $e->getMessage(),
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return back()->with('error', 'Could not send email: '.$e->getMessage());
        }
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number,'.$employee->id],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
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

    public function leaves(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $leaveType = trim((string) $request->query('leave_type', ''));
        $employeeId = (int) $request->query('employee_id', 0);
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $leavesQuery = StaffLeave::query()
            ->with('employee')
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($leaveType !== '', fn ($builder) => $builder->where('leave_type', $leaveType))
            ->when($employeeId > 0, fn ($builder) => $builder->where('employee_id', $employeeId))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->whereHas('employee', function ($employeeQuery) use ($q) {
                    $employeeQuery->where('first_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('employee_number', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('start_date');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $leavesQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loan-employee-leaves-'.now()->format('Ymd_His'),
                ['Employee', 'Employee #', 'Type', 'Start date', 'End date', 'Days', 'Status', 'Notes'],
                function () use ($rows) {
                    foreach ($rows as $leave) {
                        yield [
                            (string) ($leave->employee?->full_name ?? ''),
                            (string) ($leave->employee?->employee_number ?? ''),
                            (string) $leave->leave_type,
                            optional($leave->start_date)->format('Y-m-d') ?? '',
                            optional($leave->end_date)->format('Y-m-d') ?? '',
                            (string) $leave->days,
                            (string) ucfirst((string) $leave->status),
                            (string) ($leave->notes ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $leaves = $leavesQuery
            ->paginate($perPage)
            ->withQueryString();

        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $leaveTypes = StaffLeave::query()->whereNotNull('leave_type')->where('leave_type', '!=', '')->distinct()->orderBy('leave_type')->pluck('leave_type');

        return view('loan.employees.leaves', compact('leaves', 'employees', 'q', 'status', 'leaveType', 'employeeId', 'perPage', 'leaveTypes'));
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

    public function groups(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = min(120, max(12, (int) $request->query('per_page', 24)));

        $groupsQuery = StaffGroup::query()
            ->withCount('employees')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $groupsQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loan-employee-groups-'.now()->format('Ymd_His'),
                ['Group', 'Description', 'Members'],
                function () use ($rows) {
                    foreach ($rows as $group) {
                        yield [
                            (string) $group->name,
                            (string) ($group->description ?? ''),
                            (string) $group->employees_count,
                        ];
                    }
                },
                $export
            );
        }

        $groups = $groupsQuery
            ->paginate($perPage)
            ->withQueryString();

        return view('loan.employees.groups', compact('groups', 'q', 'perPage'));
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

    public function portfolios(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $employeeId = (int) $request->query('employee_id', 0);
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $portfoliosQuery = StaffPortfolio::query()
            ->with('employee')
            ->when($employeeId > 0, fn ($builder) => $builder->where('employee_id', $employeeId))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where('portfolio_code', 'like', '%'.$q.'%')
                    ->orWhereHas('employee', function ($employeeQuery) use ($q) {
                        $employeeQuery->where('first_name', 'like', '%'.$q.'%')
                            ->orWhere('last_name', 'like', '%'.$q.'%')
                            ->orWhere('employee_number', 'like', '%'.$q.'%');
                    });
            })
            ->orderBy('portfolio_code');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $portfoliosQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loan-employee-portfolios-'.now()->format('Ymd_His'),
                ['Employee', 'Employee #', 'Portfolio code', 'Active loans', 'Outstanding', 'PAR %'],
                function () use ($rows) {
                    foreach ($rows as $portfolio) {
                        yield [
                            (string) ($portfolio->employee?->full_name ?? ''),
                            (string) ($portfolio->employee?->employee_number ?? ''),
                            (string) $portfolio->portfolio_code,
                            (string) $portfolio->active_loans,
                            (string) ($portfolio->outstanding_amount ?? ''),
                            (string) ($portfolio->par_rate ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $portfolios = $portfoliosQuery
            ->paginate($perPage)
            ->withQueryString();

        $employees = Employee::query()->orderBy('last_name')->orderBy('first_name')->get();

        return view('loan.employees.portfolios', compact('portfolios', 'employees', 'q', 'employeeId', 'perPage'));
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

    public function loanApplications(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $stage = trim((string) $request->query('stage', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $applicationsQuery = StaffLoanApplication::query()
            ->with('employee')
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($stage !== '', fn ($builder) => $builder->where('stage', $stage))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('product', 'like', '%'.$q.'%')
                        ->orWhereHas('employee', function ($employeeQuery) use ($q) {
                            $employeeQuery->where('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%')
                                ->orWhere('employee_number', 'like', '%'.$q.'%');
                        });
                });
            })
            ->orderByDesc('created_at');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $applicationsQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loan-employee-loan-applications-'.now()->format('Ymd_His'),
                ['Reference', 'Employee', 'Employee #', 'Product', 'Amount', 'Stage', 'Status', 'Submitted'],
                function () use ($rows) {
                    foreach ($rows as $application) {
                        yield [
                            (string) ($application->reference ?? ''),
                            (string) ($application->employee?->full_name ?? ''),
                            (string) ($application->employee?->employee_number ?? ''),
                            (string) $application->product,
                            (string) $application->amount,
                            (string) $application->stage,
                            (string) ucfirst((string) $application->status),
                            optional($application->created_at)->format('Y-m-d H:i:s') ?? '',
                        ];
                    }
                },
                $export
            );
        }

        $applications = $applicationsQuery
            ->paginate($perPage)
            ->withQueryString();

        $stages = StaffLoanApplication::query()->whereNotNull('stage')->where('stage', '!=', '')->distinct()->orderBy('stage')->pluck('stage');

        return view('loan.employees.loan-applications', compact('applications', 'q', 'status', 'stage', 'perPage', 'stages'));
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

    public function staffLoans(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $loansQuery = StaffLoan::query()
            ->with('employee')
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('account_ref', 'like', '%'.$q.'%')
                        ->orWhereHas('employee', function ($employeeQuery) use ($q) {
                            $employeeQuery->where('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%')
                                ->orWhere('employee_number', 'like', '%'.$q.'%');
                        });
                });
            })
            ->orderByDesc('created_at');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $loansQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loan-employee-staff-loans-'.now()->format('Ymd_His'),
                ['Account', 'Employee', 'Employee #', 'Principal', 'Balance', 'Next due date', 'Status'],
                function () use ($rows) {
                    foreach ($rows as $loan) {
                        yield [
                            (string) ($loan->account_ref ?? ''),
                            (string) ($loan->employee?->full_name ?? ''),
                            (string) ($loan->employee?->employee_number ?? ''),
                            (string) $loan->principal,
                            (string) $loan->balance,
                            optional($loan->next_due_date)->format('Y-m-d') ?? '',
                            (string) ucfirst((string) $loan->status),
                        ];
                    }
                },
                $export
            );
        }

        $loans = $loansQuery
            ->paginate($perPage)
            ->withQueryString();

        return view('loan.employees.staff-loans', compact('loans', 'q', 'status', 'perPage'));
    }

    public function employeesBulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        try {
            $affected = Employee::query()->whereIn('id', $data['ids'])->delete();
        } catch (QueryException $e) {
            // MySQL FK restriction (e.g. accounting_salary_advances.employee_id).
            if ((string) $e->getCode() === '23000') {
                return back()->with(
                    'error',
                    'Cannot delete selected employees because they are referenced in other records (for example salary advances). Remove dependent records first, then retry.'
                );
            }

            throw $e;
        }

        return back()->with('status', 'Deleted '.$affected.' employee record(s).');
    }

    public function leavesBulkStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $affected = StaffLeave::query()->whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return back()->with('status', 'Updated '.$affected.' leave record(s).');
    }

    public function portfoliosBulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $affected = StaffPortfolio::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('status', 'Deleted '.$affected.' portfolio assignment(s).');
    }

    public function loanApplicationsBulkStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
            'status' => ['required', 'in:pending,approved,declined,disbursed'],
        ]);

        $affected = StaffLoanApplication::query()->whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return back()->with('status', 'Updated '.$affected.' staff loan application(s).');
    }

    public function staffLoansBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
            'action' => ['required', 'in:current,arrears,closed,delete'],
        ]);

        if ($data['action'] === 'delete') {
            $affected = StaffLoan::query()->whereIn('id', $data['ids'])->delete();

            return back()->with('status', 'Deleted '.$affected.' staff loan record(s).');
        }

        $affected = StaffLoan::query()->whereIn('id', $data['ids'])->update(['status' => $data['action']]);

        return back()->with('status', 'Updated '.$affected.' staff loan record(s).');
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

    private function generateNextEmployeeNumber(): string
    {
        $maxNumeric = 1000;

        $allEmployeeNumbers = Employee::query()->pluck('employee_number');
        foreach ($allEmployeeNumbers as $employeeNumber) {
            if (preg_match('/(\d+)$/', (string) $employeeNumber, $matches) === 1) {
                $maxNumeric = max($maxNumeric, (int) $matches[1]);
            }
        }

        $next = $maxNumeric + 1;
        do {
            $candidate = 'EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $exists = Employee::query()->where('employee_number', $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    private function generateReadableTempPassword(int $length = 10): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max = strlen($chars) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

    private function isLikelyDeliverableEmail(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = Str::lower((string) Str::after($email, '@'));
        if ($domain === '' || Str::endsWith($domain, '.local')) {
            return false;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function departmentOptions()
    {
        $roleDepartmentMap = self::roleDepartmentMap();
        $departmentNames = collect(array_values($roleDepartmentMap));

        if (Schema::hasTable('loan_departments')) {
            $departmentNames = $departmentNames->merge(
                LoanDepartment::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name')
            );
        }

        return $departmentNames
            ->merge(
                Employee::query()
                    ->whereNotNull('department')
                    ->where('department', '!=', '')
                    ->distinct()
                    ->orderBy('department')
                    ->pluck('department')
            )
            ->filter(fn ($name) => trim((string) $name) !== '')
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function jobTitleOptions()
    {
        $titles = collect(array_values(self::roleJobTitleMap()));
        if (Schema::hasTable('loan_job_titles')) {
            $titles = $titles->merge(
                LoanJobTitle::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name')
            );
        }

        return $titles
            ->merge(
                Employee::query()
                    ->whereNotNull('job_title')
                    ->where('job_title', '!=', '')
                    ->distinct()
                    ->orderBy('job_title')
                    ->pluck('job_title')
            )
            ->filter(fn ($name) => trim((string) $name) !== '')
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();
    }
}
