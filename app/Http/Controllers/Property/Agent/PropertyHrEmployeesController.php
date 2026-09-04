<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\Concerns\RespondsWithPropertyFormModal;
use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\Employee;
use App\Models\User;
use App\Services\Property\PropertyHrEmployeeService;
use App\Services\Property\PropertyMoney;
use App\Support\Property\PropertyEntityHub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyHrEmployeesController extends Controller
{
    use RespondsWithPropertyFormModal;

    public function __construct(
        private readonly PropertyHrEmployeeService $hr,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->indexFilters($request);
        $query = $this->hr->queryForActor($request->user());

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($inner) use ($search) {
                $inner->where('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($filters['department'] !== '') {
            $query->where('department', $filters['department']);
        }

        if ($filters['job_title'] !== '') {
            $query->where('job_title', $filters['job_title']);
        }

        if ($filters['employment_status'] !== '') {
            $query->where('employment_status', $filters['employment_status']);
        }

        if ($filters['role_type'] === 'field_officer') {
            $query->where(function ($inner) {
                $inner->where('job_title', PropertyHrEmployeeService::FIELD_OFFICER_JOB_TITLE)
                    ->orWhereHas('fieldOfficerProfile');
            });

            if ($filters['portfolio'] === 'assigned') {
                $query->whereHas('fieldOfficerProfile.properties');
            } elseif ($filters['portfolio'] === 'unassigned') {
                $query->whereHas('fieldOfficerProfile', fn ($inner) => $inner->whereDoesntHave('properties'));
            }
        }

        if ($filters['agent_user_id'] > 0 && ! AgentWorkspaceScope::shouldApply()) {
            $query->where('agent_user_id', $filters['agent_user_id']);
        }

        $employees = $query->with('fieldOfficerProfile')->get();
        $fieldOfficerCount = $employees->filter(fn (Employee $e) => $e->fieldOfficerProfile || $this->hr->isFieldOfficerJobTitle($e->job_title))->count();

        $isFieldOfficerList = $filters['role_type'] === 'field_officer';
        $tableRows = [];
        $tableRowFilters = [];
        $totalProperties = 0;
        $totalUnits = 0;
        $totalTenants = 0;
        $totalRent = 0.0;

        foreach ($employees as $employee) {
            $showUrl = route('property.hr.employees.show', ['employee' => $employee->id], false);
            $isFieldOfficer = $employee->fieldOfficerProfile || $this->hr->isFieldOfficerJobTitle($employee->job_title);
            $portfolioStats = $employee->fieldOfficerProfile?->portfolioStats() ?? [];

            if ($isFieldOfficerList && $isFieldOfficer) {
                $totalProperties += (int) ($portfolioStats['properties'] ?? 0);
                $totalUnits += (int) ($portfolioStats['units'] ?? 0);
                $totalTenants += (int) ($portfolioStats['tenants'] ?? 0);
                $totalRent += (float) ($portfolioStats['rent_portfolio'] ?? 0);
            }

            if ($isFieldOfficerList) {
                $portfolioUrl = route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'], false);
                $tableRows[] = [
                    new HtmlString('<span class="font-mono text-xs text-slate-600 dark:text-slate-400">'.e((string) $employee->employee_number).'</span>'),
                    new HtmlString(
                        '<a href="'.e($portfolioUrl).'" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700 dark:hover:text-blue-400 break-words">'.
                        e($employee->full_name).
                        '</a>'
                    ),
                    (string) ($portfolioStats['properties'] ?? 0),
                    (string) ($portfolioStats['units'] ?? 0),
                    (string) ($portfolioStats['tenants'] ?? 0),
                    PropertyMoney::kes((float) ($portfolioStats['rent_portfolio'] ?? 0)),
                    (string) ($employee->phone ?: ($employee->email ?: '—')),
                ];
            } else {
                $tableRows[] = [
                    new HtmlString('<span class="font-mono text-xs text-slate-600 dark:text-slate-400">'.e((string) $employee->employee_number).'</span>'),
                    new HtmlString(
                        '<a href="'.e($showUrl).'" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700 dark:hover:text-blue-400 break-words">'.
                        e($employee->full_name).
                        '</a>'.
                        ($isFieldOfficer ? '<div class="mt-0.5"><span class="property-status-pill property-status-pill--notice text-[10px]">Field officer</span></div>' : '')
                    ),
                    (string) ($employee->department ?: '—'),
                    (string) ($employee->job_title ?: '—'),
                    (string) ($employee->employment_status ?: '—'),
                    (string) ($employee->phone ?: ($employee->email ?: '—')),
                    $isFieldOfficer
                        ? new HtmlString('<a href="'.e(route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'], false)).'" data-turbo-frame="property-main" class="text-xs font-medium text-blue-600 hover:underline">View portfolio</a>')
                        : new HtmlString('<span class="text-xs text-slate-400">—</span>'),
                ];
            }

            $tableRowFilters[] = mb_strtolower($employee->employee_number.' '.$employee->full_name.' '.$employee->email.' '.$employee->phone);
        }

        $stats = $isFieldOfficerList
            ? [
                ['label' => 'Field officers', 'value' => (string) $employees->count(), 'hint' => 'Matching filters'],
                ['label' => 'Properties', 'value' => (string) $totalProperties, 'hint' => 'Assigned portfolio'],
                ['label' => 'Units', 'value' => (string) $totalUnits, 'hint' => 'Across portfolios'],
                ['label' => 'Rent portfolio', 'value' => PropertyMoney::kes($totalRent), 'hint' => 'Active lease rent'],
            ]
            : [
                ['label' => 'Employees', 'value' => (string) $employees->count(), 'hint' => 'Matching filters'],
                ['label' => 'Field officers', 'value' => (string) $fieldOfficerCount, 'hint' => 'With portfolio role'],
                ['label' => 'Active', 'value' => (string) $employees->where('employment_status', 'active')->count(), 'hint' => 'Employment status'],
                ['label' => 'Departments', 'value' => (string) $employees->pluck('department')->filter()->unique()->count(), 'hint' => 'In result set'],
            ];

        $columns = $isFieldOfficerList
            ? ['Number', 'Name', 'Properties', 'Units', 'Tenants', 'Rent portfolio', 'Contact']
            : ['Number', 'Name', 'Department', 'Job title', 'Status', 'Contact', 'Portfolio'];

        return property_view('property.agent.hr.employees.index', [
            'filters' => $filters,
            'agents' => $this->agentOptionsForForm($request),
            'departments' => PropertyHrEmployeeService::DEPARTMENTS,
            'jobTitles' => PropertyHrEmployeeService::JOB_TITLES,
            'stats' => $stats,
            'columns' => $columns,
            'tableRows' => $tableRows,
            'tableRowFilters' => $tableRowFilters,
            'columnConfig' => [],
            'isFieldOfficerList' => $isFieldOfficerList,
        ]);
    }

    public function create(Request $request): View
    {
        return property_view('property.agent.hr.employees.create', array_merge([
            'agents' => $this->agentOptionsForForm($request),
            'defaultAgentUserId' => $this->defaultAgentUserId($request),
            'departments' => PropertyHrEmployeeService::DEPARTMENTS,
            'jobTitles' => PropertyHrEmployeeService::JOB_TITLES,
            'suggestedEmployeeNumber' => $this->hr->generateNextEmployeeNumber(),
            'defaultJobTitle' => (string) $request->query('job_title', ''),
            'defaultIsFieldOfficer' => $request->boolean('field_officer') || $request->query('job_title') === PropertyHrEmployeeService::FIELD_OFFICER_JOB_TITLE,
            'propertyRoles' => $this->hr->propertyRolesForForm(),
            'rolesReady' => $this->hr->propertyRolesForForm()->isNotEmpty(),
        ], $this->propertyFormModalViewData($request)));
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $agentUserId = $this->hr->resolveAgentUserIdForStore($request);
        $validated = $this->validateEmployee($request, null, $agentUserId);
        $provision = null;

        DB::transaction(function () use ($validated, $agentUserId, $request, &$provision): void {
            $employee = Employee::query()->create([
                ...$validated['employee'],
                'agent_user_id' => $agentUserId,
                'employee_number' => $validated['employee']['employee_number'] ?: $this->hr->generateNextEmployeeNumber(),
            ]);

            if ($validated['provision_login']) {
                $provision = $this->hr->provisionPropertyLogin(
                    $employee->fresh(),
                    $validated['role_ids'],
                    $request->user(),
                );
                $employee->refresh();
            }

            $this->hr->syncFieldOfficerFromEmployee(
                $employee->fresh(),
                $validated['is_field_officer'],
                $validated['portal_access'],
            );
        });

        $redirect = redirect()->route('property.hr.employees.index')->with('status', 'Employee added.');
        if ($provision !== null) {
            $redirect->with('hr_user_created', [
                'email' => $provision['user']->email,
                'temporary_password' => $provision['plain_password'],
                'name' => $provision['user']->name,
            ])->with('status', 'Employee added and portal login created. Share the temporary password securely.');
        }

        return $this->redirectOrPropertyFormModalSuccess(
            $request,
            $redirect,
            $provision !== null ? 'Employee added and portal login created.' : 'Employee added.',
        );
    }

    public function show(Request $request, Employee $employee): View
    {
        $employee->loadMissing(['fieldOfficerProfile', 'supervisor', 'agentUser', 'user.pmRoles', 'staffLeaves' => fn ($q) => $q->orderByDesc('start_date')->limit(5)]);

        $isFieldOfficer = $this->hr->isFieldOfficerEmployee($employee);
        $fieldOfficer = $isFieldOfficer ? $this->hr->resolveFieldOfficerForEmployee($employee) : null;
        $activeTab = PropertyEntityHub::normalizeTab('employee', $request->query('tab'));

        if ($activeTab === 'portfolio' && ! $isFieldOfficer) {
            $activeTab = 'overview';
        }

        $portfolioStats = $fieldOfficer?->portfolioStats();
        $assignedProperties = $fieldOfficer ? $this->hr->assignedPropertyRows($fieldOfficer) : [];
        $unassignedProperties = $fieldOfficer ? $this->hr->unassignedPropertiesForOfficer($fieldOfficer) : [];
        $canManage = auth()->check() && auth()->user()?->hasPmPermission('properties.manage');

        return property_view('property.agent.hr.employees.show', [
            'employee' => $employee,
            'fieldOfficer' => $fieldOfficer,
            'isFieldOfficer' => $isFieldOfficer,
            'activeTab' => $activeTab,
            'employeeTabs' => PropertyEntityHub::employeeTabsFor($isFieldOfficer),
            'portfolioStats' => $portfolioStats,
            'assignedProperties' => $assignedProperties,
            'unassignedProperties' => $unassignedProperties,
            'canManage' => $canManage,
            'recentLeaves' => $employee->staffLeaves,
        ]);
    }

    public function assignProperty(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
        ]);

        $property = $this->hr->assignPropertyToEmployee($employee, (int) $data['property_id']);

        return redirect()
            ->route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'])
            ->with('status', 'Property "'.$property->name.'" assigned.');
    }

    public function detachProperty(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
        ]);

        $property = $this->hr->detachPropertyFromEmployee($employee, (int) $data['property_id']);

        return redirect()
            ->route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'])
            ->with('status', 'Property "'.$property->name.'" unassigned.');
    }

    public function edit(Request $request, Employee $employee): View
    {
        $employee->loadMissing('fieldOfficerProfile');

        return property_view('property.agent.hr.employees.edit', array_merge([
            'employee' => $employee,
            'agents' => $this->agentOptionsForForm($request),
            'departments' => PropertyHrEmployeeService::DEPARTMENTS,
            'jobTitles' => PropertyHrEmployeeService::JOB_TITLES,
            'isFieldOfficer' => (bool) $employee->fieldOfficerProfile || $this->hr->isFieldOfficerJobTitle($employee->job_title),
            'propertyRoles' => $this->hr->propertyRolesForForm(),
            'rolesReady' => $this->hr->propertyRolesForForm()->isNotEmpty(),
            'linkedRoleIds' => $employee->user?->pmRoles?->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [],
        ], $this->propertyFormModalViewData($request)));
    }

    public function update(Request $request, Employee $employee): RedirectResponse|Response
    {
        $agentUserId = AgentWorkspaceScope::shouldApply()
            ? (int) ($employee->agent_user_id ?: $request->user()->id)
            : (int) $request->input('agent_user_id', $employee->agent_user_id ?: $request->user()->id);

        $validated = $this->validateEmployee($request, $employee, $agentUserId);
        $provision = null;

        DB::transaction(function () use ($employee, $validated, $agentUserId, $request, &$provision): void {
            $employee->update([
                ...$validated['employee'],
                'agent_user_id' => $agentUserId,
            ]);

            if ($validated['provision_login'] && ! $employee->user_id) {
                $provision = $this->hr->provisionPropertyLogin(
                    $employee->fresh(),
                    $validated['role_ids'],
                    $request->user(),
                );
            } elseif ($employee->user_id && $validated['role_ids'] !== []) {
                $employee->user?->pmRoles()?->sync($validated['role_ids']);
            }

            $this->hr->syncFieldOfficerFromEmployee(
                $employee->fresh(),
                $validated['is_field_officer'],
                $validated['portal_access'],
            );
        });

        $redirect = redirect()->route('property.hr.employees.show', $employee)->with('status', 'Employee updated.');
        if ($provision !== null) {
            $redirect->with('hr_user_created', [
                'email' => $provision['user']->email,
                'temporary_password' => $provision['plain_password'],
                'name' => $provision['user']->name,
            ])->with('status', 'Employee updated and portal login created.');
        }

        return $this->redirectOrPropertyFormModalSuccess(
            $request,
            $redirect,
            $provision !== null ? 'Employee updated and portal login created.' : 'Employee updated.',
        );
    }

    /**
     * @return array{
     *     q: string,
     *     department: string,
     *     job_title: string,
     *     employment_status: string,
     *     role_type: string,
     *     portfolio: string,
     *     agent_user_id: int
     * }
     */
    private function indexFilters(Request $request): array
    {
        $portfolio = (string) $request->query('portfolio', 'all');
        if (! in_array($portfolio, ['all', 'assigned', 'unassigned'], true)) {
            $portfolio = 'all';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'department' => trim((string) $request->query('department', '')),
            'job_title' => trim((string) $request->query('job_title', '')),
            'employment_status' => trim((string) $request->query('employment_status', '')),
            'role_type' => (string) $request->query('role_type', 'all'),
            'portfolio' => $portfolio,
            'agent_user_id' => (int) $request->query('agent_user_id', 0),
        ];
    }

    /**
     * @return array{
     *     employee: array<string, mixed>,
     *     is_field_officer: bool,
     *     portal_access: bool,
     *     provision_login: bool,
     *     role_ids: list<int>
     * }
     */
    private function validateEmployee(Request $request, ?Employee $employee, int $agentUserId): array
    {
        $employeeId = $employee?->id;

        $validated = $request->validate([
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')->ignore($employeeId),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'employment_status' => ['nullable', 'string', 'max:40'],
            'hire_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:40'],
            'is_field_officer' => ['nullable', 'boolean'],
            'portal_access' => ['nullable', 'boolean'],
            'provision_login' => ['nullable', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['nullable', 'integer', 'exists:pm_roles,id'],
            'agent_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $isFieldOfficer = $request->boolean('is_field_officer')
            || $this->hr->isFieldOfficerJobTitle($validated['job_title'] ?? null);

        $provisionLogin = $request->boolean('provision_login');
        $roleIds = array_values(array_unique(array_filter(array_map('intval', (array) ($validated['role_ids'] ?? [])))));

        if ($provisionLogin && $roleIds === [] && ! $employee?->user_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'role_ids' => 'Select a property role when creating a portal login.',
            ]);
        }

        return [
            'employee' => [
                'employee_number' => trim((string) ($validated['employee_number'] ?? '')),
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'email' => trim((string) ($validated['email'] ?? '')) ?: null,
                'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
                'department' => trim((string) ($validated['department'] ?? '')) ?: null,
                'job_title' => trim((string) ($validated['job_title'] ?? '')) ?: null,
                'employment_status' => trim((string) ($validated['employment_status'] ?? '')) ?: 'active',
                'hire_date' => $validated['hire_date'] ?? null,
                'national_id' => trim((string) ($validated['national_id'] ?? '')) ?: null,
            ],
            'is_field_officer' => $isFieldOfficer,
            'portal_access' => $request->boolean('portal_access'),
            'provision_login' => $provisionLogin,
            'role_ids' => $roleIds,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function agentOptionsForForm(Request $request): array
    {
        if (AgentWorkspaceScope::shouldApply()) {
            return [];
        }

        return User::query()
            ->where(function ($q) {
                $q->where('property_portal_role', 'agent')
                    ->orWhereIn('id', function ($sub) {
                        $sub->select('agent_user_id')->from('properties')->whereNotNull('agent_user_id');
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
            ->values()
            ->all();
    }

    private function defaultAgentUserId(Request $request): int
    {
        if (AgentWorkspaceScope::shouldApply()) {
            return (int) $request->user()->id;
        }

        $agents = $this->agentOptionsForForm($request);

        return (int) ($agents[0]['id'] ?? $request->user()->id);
    }
}
