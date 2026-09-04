<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\Concerns\RespondsWithPropertyFormModal;
use App\Models\Employee;
use App\Models\StaffLeave;
use App\Services\Property\PropertyHrEmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyHrLeavesController extends Controller
{
    use RespondsWithPropertyFormModal;

    public function __construct(
        private readonly PropertyHrEmployeeService $hr,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'employee_id' => (int) $request->query('employee_id', 0),
        ];

        $employeeIds = $this->hr->employeeIdsForActor($request->user());

        $leavesQuery = StaffLeave::query()
            ->with('employee')
            ->whereIn('employee_id', $employeeIds)
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['employee_id'] > 0, fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $search = $filters['q'];
                $q->whereHas('employee', function ($inner) use ($search) {
                    $inner->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('employee_number', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('start_date');

        $leaves = $leavesQuery->get();
        $employees = $this->hr->queryForActor($request->user())->get(['id', 'first_name', 'last_name', 'employee_number']);

        $tableRows = [];
        $tableRowFilters = [];

        foreach ($leaves as $leave) {
            $employee = $leave->employee;
            $employeeUrl = $employee
                ? route('property.hr.employees.show', ['employee' => $employee->id], false)
                : '#';

            $statusClass = match ((string) $leave->status) {
                'approved' => 'property-status-pill--occupied',
                'rejected' => 'property-status-pill--attention',
                default => 'property-status-pill--notice',
            };

            $tableRows[] = [
                $employee
                    ? new HtmlString('<a href="'.e($employeeUrl).'" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700">'.e($employee->full_name).'</a><div class="text-xs text-slate-500">'.e((string) $employee->employee_number).'</div>')
                    : '—',
                (string) $leave->leave_type,
                optional($leave->start_date)->format('Y-m-d') ?? '—',
                optional($leave->end_date)->format('Y-m-d') ?? '—',
                (string) ($leave->days ?? '—'),
                new HtmlString('<span class="property-status-pill '.$statusClass.'">'.e(ucfirst((string) $leave->status)).'</span>'),
                new HtmlString(
                    auth()->user()?->hasPmPermission('properties.manage')
                        ? view('property.agent.hr.leaves.partials.row_actions', ['leave' => $leave])->render()
                        : '—'
                ),
            ];
            $tableRowFilters[] = mb_strtolower(($employee?->full_name ?? '').' '.$leave->leave_type);
        }

        return property_view('property.agent.hr.leaves.index', [
            'filters' => $filters,
            'employees' => $employees,
            'stats' => [
                ['label' => 'Leave requests', 'value' => (string) $leaves->count(), 'hint' => 'Matching filters'],
                ['label' => 'Pending', 'value' => (string) $leaves->where('status', 'pending')->count(), 'hint' => 'Awaiting action'],
                ['label' => 'Approved', 'value' => (string) $leaves->where('status', 'approved')->count(), 'hint' => 'Approved leave'],
            ],
            'columns' => ['Employee', 'Type', 'Start', 'End', 'Days', 'Status', 'Actions'],
            'tableRows' => $tableRows,
            'tableRowFilters' => $tableRowFilters,
            'columnConfig' => [],
        ]);
    }

    public function create(Request $request): View
    {
        return property_view('property.agent.hr.leaves.create', array_merge([
            'employees' => $this->hr->queryForActor($request->user())->get(),
            'leaveTypes' => PropertyHrEmployeeService::LEAVE_TYPES,
            'defaultEmployeeId' => (int) $request->query('employee_id', 0),
        ], $this->propertyFormModalViewData($request)));
    }

    public function store(Request $request): RedirectResponse
    {
        $employeeIds = $this->hr->employeeIdsForActor($request->user());

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::in($employeeIds)],
            'leave_type' => ['required', 'string', 'max:40'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();

        StaffLeave::query()->create([
            'employee_id' => (int) $validated['employee_id'],
            'leave_type' => $validated['leave_type'],
            'start_date' => $start,
            'end_date' => $end,
            'days' => (int) $start->diffInDays($end) + 1,
            'status' => 'pending',
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        return redirect()
            ->route('property.hr.leaves.index')
            ->with('status', 'Leave request submitted.');
    }

    public function updateStatus(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        $employeeIds = $this->hr->employeeIdsForActor($request->user());
        abort_unless(in_array((int) $staffLeave->employee_id, $employeeIds, true), 404);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $staffLeave->update(['status' => $validated['status']]);

        return back()->with('status', 'Leave status updated.');
    }
}
