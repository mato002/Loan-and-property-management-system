<?php

namespace App\Services\Property;

use App\Models\Employee;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmMessageLog;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyHrWorkflowService
{
    public function approvalThreshold(): float
    {
        return max(0, (float) PropertyPortalSetting::getValue('workflow_maintenance_approval_threshold', '100'));
    }

    /**
     * @return Collection<int, Employee>
     */
    public function assignedEmployees(int $propertyId): Collection
    {
        if ($propertyId <= 0 || ! Schema::hasTable('employees')) {
            return collect();
        }

        $ids = collect();
        if (Schema::hasTable('employee_property_assignments')) {
            $ids = $ids->merge(
                DB::table('employee_property_assignments')
                    ->where('property_id', $propertyId)
                    ->pluck('employee_id')
            );
        }

        if (Schema::hasColumn('properties', 'field_officer_id') && Schema::hasTable('pm_field_officers')) {
            $officerId = DB::table('properties')->where('id', $propertyId)->value('field_officer_id');
            if ($officerId) {
                $employeeId = DB::table('pm_field_officers')->where('id', $officerId)->value('employee_id');
                if ($employeeId) {
                    $ids->push($employeeId);
                }
            }
        }

        $ids = $ids->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->whereIn('id', $ids->all())
            ->where('employment_status', 'active')
            ->whereNotNull('user_id')
            ->with(['user.pmRoles.permissions'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, User>
     */
    public function usersHolding(Collection $employees, string $permissionKey): Collection
    {
        return $employees
            ->map(fn (Employee $employee) => $employee->user)
            ->filter(fn ($user) => $user instanceof User && $this->explicitlyHolds($user, $permissionKey))
            ->unique('id')
            ->values();
    }

    public function explicitlyHolds(User $user, string $permissionKey): bool
    {
        if (($user->is_super_admin ?? false) === true) {
            return true;
        }
        if ($user->isTerminatedEmployee()) {
            return false;
        }

        $effect = $user->directPmPermissionEffect($permissionKey);
        if ($effect === 'deny') {
            return false;
        }
        if ($effect === 'allow') {
            return true;
        }

        $roles = $user->relationLoaded('pmRoles')
            ? $user->pmRoles
            : $user->pmRoles()->with('permissions:id,key')->get();

        if ($roles->isEmpty()) {
            return false;
        }

        return $roles
            ->flatMap(fn ($role) => $role->permissions->pluck('key'))
            ->contains($permissionKey);
    }

    public function routeMaintenanceRequest(PmMaintenanceRequest $request): ?string
    {
        $request->loadMissing('unit.property', 'property');
        $propertyId = (int) ($request->property_id ?: $request->unit?->property_id ?: 0);
        $staff = $this->usersHolding($this->assignedEmployees($propertyId), 'maintenance.resolve');
        if ($staff->isEmpty()) {
            return null;
        }

        $assignee = $staff->first();
        $updates = [];
        if (Schema::hasColumn('pm_maintenance_requests', 'assigned_user_id')) {
            $updates['assigned_user_id'] = $assignee->id;
        }
        if ((string) $request->status === 'open') {
            $updates['status'] = 'in_progress';
        }
        if ($updates !== []) {
            $request->update($updates);
        }

        $propertyName = (string) ($request->unit?->property?->name ?? 'a property');
        $unitLabel = (string) ($request->unit?->label ?? 'Whole property');
        $subject = 'Maintenance request #'.$request->id.' assigned';
        $body = 'A '.$request->urgency.' '.$request->category.' request at '.$propertyName.' / '.$unitLabel.' was routed to you because you can resolve maintenance on this property.';

        foreach ($staff as $user) {
            $this->notifyUser($user, $subject, $body);
        }

        PropertyActivityLogger::record('maintenance.routed', $subject.' to '.$staff->pluck('name')->join(', '), [
            'source' => 'maintenance',
            'entity_type' => 'pm_maintenance_request',
            'entity_id' => (int) $request->id,
            'actor_user_id' => $assignee->id,
            'payload' => [
                'property_id' => $propertyId,
                'notified_user_ids' => $staff->pluck('id')->all(),
            ],
        ]);

        return $staff->pluck('name')->join(', ');
    }

    /**
     * @return array{status: string, notice: ?string}
     */
    public function decideMaintenanceApproval(?PmMaintenanceJob $job, ?User $actor, string $status, float $quote, bool $isNew = false, ?int $maintenanceRequestId = null): array
    {
        $threshold = $this->approvalThreshold();
        $canApprove = $actor ? $this->explicitlyHolds($actor, 'maintenance.approve_high_value') : false;
        $above = $quote > $threshold + 0.0001;
        $previous = $job ? (float) ($job->getOriginal('quote_amount') ?? 0) : 0;
        $crossed = $isNew || $previous <= $threshold + 0.0001;
        $blocked = $above && $status === 'approved' && ! $canApprove;

        if ($blocked) {
            $status = 'quoted';
        }

        if ($above && ! $canApprove && ($blocked || $crossed)) {
            $this->notifyHighValueApprovers($job, $quote, $threshold, $maintenanceRequestId);
        }

        $notice = null;
        if ($blocked) {
            $notice = 'This estimate is above '.PropertyMoney::kes($threshold).'. It was left as quoted and sent to someone who can approve high-value repairs.';
        }

        return ['status' => $status, 'notice' => $notice];
    }

    public function logMaintenanceClosed(PmMaintenanceJob $job, ?User $actor): void
    {
        $job->loadMissing('request.unit.property');
        $unit = (string) ($job->request?->unit?->property?->name ?? 'property');
        PropertyActivityLogger::record('maintenance.closed', 'Maintenance job #'.$job->id.' closed at '.$unit, [
            'source' => 'maintenance',
            'entity_type' => 'pm_maintenance_job',
            'entity_id' => (int) $job->id,
            'actor_user_id' => $actor?->id,
            'portal_role' => $actor?->property_portal_role,
            'payload' => [
                'quote_amount' => (float) ($job->quote_amount ?? 0),
                'status' => (string) $job->status,
            ],
        ]);
    }

    public function logPaymentRecorded(int $paymentId, int $tenantId, float $amount, ?int $invoiceId, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        PropertyActivityLogger::record('payment.recorded', 'Payment #'.$paymentId.' of '.PropertyMoney::kes($amount).' recorded', [
            'source' => 'payment',
            'entity_type' => 'pm_payment',
            'entity_id' => $paymentId,
            'pm_tenant_id' => $tenantId ?: null,
            'pm_invoice_id' => $invoiceId ?: null,
            'actor_user_id' => $actor->id,
            'portal_role' => $actor->property_portal_role,
            'payload' => ['amount' => $amount],
        ]);
    }

    public function logLeaseCreated(int $leaseId, ?int $tenantId, ?User $actor): void
    {
        PropertyActivityLogger::record('lease.created', 'Lease #'.$leaseId.' generated', [
            'source' => 'lease',
            'entity_type' => 'pm_lease',
            'entity_id' => $leaseId,
            'pm_lease_id' => $leaseId,
            'pm_tenant_id' => $tenantId ?: null,
            'actor_user_id' => $actor?->id,
            'portal_role' => $actor?->property_portal_role,
        ]);
    }

    private function notifyHighValueApprovers(?PmMaintenanceJob $job, float $quote, float $threshold, ?int $maintenanceRequestId = null): void
    {
        $propertyId = 0;
        $requestId = (int) ($maintenanceRequestId ?: ($job?->pm_maintenance_request_id ?? 0));
        if ($requestId > 0) {
            $unitId = (int) (DB::table('pm_maintenance_requests')->where('id', $requestId)->value('property_unit_id') ?? 0);
            if ($unitId > 0) {
                $propertyId = (int) (PropertyUnit::query()->withoutGlobalScopes()->whereKey($unitId)->value('property_id') ?? 0);
            }
        }

        $approvers = $this->usersHolding($this->assignedEmployees($propertyId), 'maintenance.approve_high_value');
        if ($approvers->isEmpty() && $propertyId > 0) {
            $agentId = (int) (DB::table('properties')->where('id', $propertyId)->value('agent_user_id') ?? 0);
            if ($agentId > 0) {
                $companyStaff = Employee::query()
                    ->where('agent_user_id', $agentId)
                    ->where('employment_status', 'active')
                    ->whereNotNull('user_id')
                    ->with(['user.pmRoles.permissions'])
                    ->get();
                $approvers = $this->usersHolding($companyStaff, 'maintenance.approve_high_value');
            }
        }

        $subject = 'High-value repair needs approval';
        $body = 'A repair estimate of '.PropertyMoney::kes($quote).' is above the '.PropertyMoney::kes($threshold).' threshold'
            .($requestId > 0 ? ' on maintenance request #'.$requestId : '')
            .'. Approve it if you hold high-value maintenance approval.';

        foreach ($approvers as $user) {
            $this->notifyUser($user, $subject, $body);
        }
    }

    private function notifyUser(User $user, string $subject, string $body): void
    {
        if (! Schema::hasTable('pm_message_logs')) {
            return;
        }

        PmMessageLog::query()->create([
            'user_id' => $user->id,
            'channel' => 'system',
            'to_address' => (string) ($user->email ?: ('user:'.$user->id)),
            'subject' => $subject,
            'body' => $body,
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
