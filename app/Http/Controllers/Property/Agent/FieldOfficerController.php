<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PmFieldOfficer;
use App\Services\Property\PropertyHrEmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Legacy URLs — field officers are managed as HR employees.
 */
class FieldOfficerController extends Controller
{
    public function __construct(
        private readonly PropertyHrEmployeeService $hr,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->hr->backfillFieldOfficerEmployees();

        $params = $request->query();
        $params['role_type'] = 'field_officer';

        return redirect()->route('property.hr.employees.index', $params);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('property.hr.employees.create', array_merge($request->query(), [
            'field_officer' => 1,
            'job_title' => PropertyHrEmployeeService::FIELD_OFFICER_JOB_TITLE,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('property.hr.employees.create', [
            'field_officer' => 1,
            'job_title' => PropertyHrEmployeeService::FIELD_OFFICER_JOB_TITLE,
        ]);
    }

    public function show(Request $request, PmFieldOfficer $fieldOfficer): RedirectResponse
    {
        $employee = $this->resolveEmployee($fieldOfficer);
        $tab = (string) $request->query('tab', 'overview');
        if ($tab === 'properties') {
            $tab = 'portfolio';
        }

        return redirect()->route('property.hr.employees.show', array_merge(
            $request->except('tab'),
            ['employee' => $employee->id, 'tab' => $tab],
        ));
    }

    public function edit(Request $request, PmFieldOfficer $fieldOfficer): RedirectResponse
    {
        $employee = $this->resolveEmployee($fieldOfficer);

        return redirect()->route('property.hr.employees.edit', ['employee' => $employee->id] + $request->query());
    }

    public function update(Request $request, PmFieldOfficer $fieldOfficer): RedirectResponse
    {
        $employee = $this->resolveEmployee($fieldOfficer);

        return redirect()->route('property.hr.employees.edit', ['employee' => $employee->id]);
    }

    public function assignProperty(Request $request, PmFieldOfficer $fieldOfficer): RedirectResponse
    {
        $employee = $this->resolveEmployee($fieldOfficer);
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
        ]);

        $property = $this->hr->assignPropertyToEmployee($employee, (int) $data['property_id']);

        return redirect()
            ->route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'])
            ->with('status', 'Property "'.$property->name.'" assigned.');
    }

    public function detachProperty(Request $request, PmFieldOfficer $fieldOfficer): RedirectResponse
    {
        $employee = $this->resolveEmployee($fieldOfficer);
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
        ]);

        $property = $this->hr->detachPropertyFromEmployee($employee, (int) $data['property_id']);

        return redirect()
            ->route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'])
            ->with('status', 'Property "'.$property->name.'" unassigned.');
    }

    private function resolveEmployee(PmFieldOfficer $fieldOfficer): Employee
    {
        if ($fieldOfficer->employee_id) {
            return Employee::query()->findOrFail((int) $fieldOfficer->employee_id);
        }

        return $this->hr->ensureEmployeeForFieldOfficer($fieldOfficer);
    }
}
