<?php

namespace App\Services\Property;

use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PmTenantNotice;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\UnassignedPayment;
use App\Models\User;
use App\Services\Property\LandlordLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PropertyOffboardingService
{
    public function __construct(
        private readonly PropertyManagementGuardService $guard,
        private readonly FinancialReportingFormulaService $formulas,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function statusCheck(Property $property): array
    {
        $unitIds = $this->unitIds($property);
        $landlords = $property->landlords()->get();

        $activeLeases = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_id', $property->id))
            ->with(['pmTenant:id,name', 'units:id,label,property_id'])
            ->orderBy('id')
            ->get();

        $openAr = $unitIds !== []
            ? $this->formulas->unitOutstanding($unitIds)
            : 0.0;

        $openInvoiceCount = $unitIds !== []
            ? (int) PmInvoice::query()->openBillable()->whereIn('property_unit_id', $unitIds)->count()
            : 0;

        $pendingPayments = $unitIds !== []
            ? (int) PmPayment::query()
                ->where('status', PmPayment::STATUS_PENDING)
                ->whereIn('pm_tenant_id', function ($sub) use ($unitIds) {
                    $sub->select('pm_tenant_id')
                        ->from('pm_invoices')
                        ->whereIn('property_unit_id', $unitIds)
                        ->whereNotNull('pm_tenant_id');
                })
                ->count()
            : 0;

        $openMaintenance = $unitIds !== []
            ? (int) PmMaintenanceRequest::query()
                ->whereIn('property_unit_id', $unitIds)
                ->whereIn('status', ['open', 'in_progress'])
                ->count()
            : 0;

        $activeJobs = $unitIds !== []
            ? (int) PmMaintenanceJob::query()
                ->whereHas('request', fn ($q) => $q->whereIn('property_unit_id', $unitIds))
                ->whereIn('status', ['quoted', 'approved', 'in_progress'])
                ->count()
            : 0;

        $landlordPayable = $this->landlordPayableBalance($property, $landlords);

        $utilityAccounts = 0;
        if (Schema::hasTable('pm_unit_utility_charges')) {
            $utilityQuery = DB::table('pm_unit_utility_charges as c')
                ->join('property_units as u', 'u.id', '=', 'c.property_unit_id')
                ->where('u.property_id', $property->id);
            if (Schema::hasColumn('pm_unit_utility_charges', 'is_active')) {
                $utilityQuery->where('c.is_active', true);
            }
            $utilityAccounts = (int) $utilityQuery->count();
        }

        $pendingNotices = $unitIds !== []
            ? (int) PmTenantNotice::query()
                ->whereIn('property_unit_id', $unitIds)
                ->whereIn('status', ['draft', 'sent', 'pending'])
                ->count()
            : 0;

        $unmatchedPayments = Schema::hasTable('unassigned_payments')
            ? (int) UnassignedPayment::query()->count()
            : 0;

        return [
            'property_id' => (int) $property->id,
            'management_status' => $property->managementStatus(),
            'management_status_label' => $property->managementStatusLabel(),
            'active_leases_count' => $activeLeases->count(),
            'active_leases' => $activeLeases,
            'open_ar' => round($openAr, 2),
            'open_invoice_count' => $openInvoiceCount,
            'pending_payments' => $pendingPayments,
            'open_maintenance' => $openMaintenance,
            'active_maintenance_jobs' => $activeJobs,
            'landlord_payable_balance' => round($landlordPayable, 2),
            'landlords' => $landlords,
            'active_utility_accounts' => $utilityAccounts,
            'pending_notices' => $pendingNotices,
            'unmatched_payments' => $unmatchedPayments,
            'unit_count' => count($unitIds),
        ];
    }

    public function startOffboarding(Property $property, ?string $reason = null, ?string $notes = null): Property
    {
        if ($property->isManagementReadOnly()) {
            throw ValidationException::withMessages([
                'property' => 'This property is already read-only.',
            ]);
        }

        if ($property->isOffboarding()) {
            return $property;
        }

        $property->update([
            'management_status' => Property::MANAGEMENT_OFFBOARDING,
            'management_end_reason' => $reason,
            'offboarding_notes' => $notes,
        ]);

        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_PROPERTY_OFFBOARDING_STARTED,
            'property',
            (int) $property->id,
            [
                'summary' => 'Offboarding started for property '.$property->name,
                'payload' => [
                    'reason' => $reason,
                    'notes' => $notes,
                ],
            ]
        );

        return $property->fresh();
    }

    /**
     * @return array{allowed: bool, reasons: list<string>}
     */
    public function canDetachLandlord(Property $property, bool $adminOverride = false): array
    {
        $check = $this->statusCheck($property);
        $reasons = [];

        if ((int) ($check['active_leases_count'] ?? 0) > 0) {
            $reasons[] = 'Active leases must be terminated first.';
        }

        if ((float) ($check['landlord_payable_balance'] ?? 0) > 0.009 && ! $adminOverride) {
            $reasons[] = 'Landlord payable balance must be settled or an admin override is required.';
        }

        if (trim((string) $property->management_end_reason) === '' && trim((string) $property->offboarding_notes) === '') {
            $reasons[] = 'Record an offboarding reason or notes before detaching the landlord.';
        }

        return [
            'allowed' => $reasons === [] || ($adminOverride && (int) ($check['active_leases_count'] ?? 0) === 0),
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{allowed: bool, reasons: list<string>}
     */
    public function canArchive(Property $property, bool $adminOverride = false): array
    {
        $check = $this->statusCheck($property);
        $reasons = [];

        if ((int) ($check['active_leases_count'] ?? 0) > 0) {
            $reasons[] = 'Terminate all active leases before archiving.';
        }

        if ($property->landlords()->exists() && ! $adminOverride) {
            $reasons[] = 'Detach all landlords before archiving, or use admin override.';
        }

        if ((float) ($check['landlord_payable_balance'] ?? 0) > 0.009 && ! $adminOverride) {
            $reasons[] = 'Settle landlord payable balance or use admin override.';
        }

        return [
            'allowed' => $reasons === [] || $adminOverride,
            'reasons' => $reasons,
        ];
    }

    public function archive(Property $property, ?string $reason = null, bool $adminOverride = false): Property
    {
        $gate = $this->canArchive($property, $adminOverride);
        if (! $gate['allowed']) {
            throw ValidationException::withMessages([
                'property' => implode(' ', $gate['reasons']),
            ]);
        }

        if ($adminOverride && $gate['reasons'] !== []) {
            PmFinanceAuditLog::record(
                PmFinanceAuditLog::ACTION_PROPERTY_OFFBOARDING_OVERRIDE,
                'property',
                (int) $property->id,
                [
                    'summary' => 'Archive override used for property '.$property->name,
                    'payload' => ['reasons_bypassed' => $gate['reasons']],
                ]
            );
        }

        $now = now();
        $property->update([
            'management_status' => Property::MANAGEMENT_ARCHIVED,
            'management_ended_at' => $now,
            'management_end_reason' => $reason ?? $property->management_end_reason,
            'archived_at' => $now,
            'archived_by' => Auth::id(),
        ]);

        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_PROPERTY_ARCHIVED,
            'property',
            (int) $property->id,
            [
                'summary' => 'Property archived: '.$property->name,
                'payload' => [
                    'reason' => $reason ?? $property->management_end_reason,
                    'admin_override' => $adminOverride,
                ],
            ]
        );

        return $property->fresh();
    }

    public function endManagement(Property $property, ?string $reason = null, bool $adminOverride = false): Property
    {
        $gate = $this->canArchive($property, $adminOverride);
        if (! $gate['allowed']) {
            throw ValidationException::withMessages([
                'property' => implode(' ', $gate['reasons']),
            ]);
        }

        if ($adminOverride && $gate['reasons'] !== []) {
            PmFinanceAuditLog::record(
                PmFinanceAuditLog::ACTION_PROPERTY_OFFBOARDING_OVERRIDE,
                'property',
                (int) $property->id,
                [
                    'summary' => 'End-management override used for property '.$property->name,
                    'payload' => ['reasons_bypassed' => $gate['reasons']],
                ]
            );
        }

        $property->update([
            'management_status' => Property::MANAGEMENT_ENDED,
            'management_ended_at' => now(),
            'management_end_reason' => $reason ?? $property->management_end_reason,
        ]);

        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_PROPERTY_MANAGEMENT_ENDED,
            'property',
            (int) $property->id,
            [
                'summary' => 'Management ended for property '.$property->name,
                'payload' => ['reason' => $reason ?? $property->management_end_reason],
            ]
        );

        return $property->fresh();
    }

    public function restore(Property $property): Property
    {
        if (! $property->isManagementReadOnly() && ! $property->isOffboarding()) {
            return $property;
        }

        $property->update([
            'management_status' => Property::MANAGEMENT_ACTIVE,
            'management_ended_at' => null,
            'archived_at' => null,
            'archived_by' => null,
        ]);

        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_PROPERTY_RESTORED,
            'property',
            (int) $property->id,
            [
                'summary' => 'Property restored to active: '.$property->name,
            ]
        );

        return $property->fresh();
    }

    public function logLeaseTerminatedDuringOffboarding(PmLease $lease, Property $property): void
    {
        if (! $property->isOffboarding()) {
            return;
        }

        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_PROPERTY_OFFBOARDING_LEASE_TERMINATED,
            'property',
            (int) $property->id,
            [
                'pm_lease_id' => (int) $lease->id,
                'pm_tenant_id' => (int) $lease->pm_tenant_id,
                'summary' => 'Lease #'.$lease->id.' terminated during offboarding',
            ]
        );
    }

    public function logLandlordDetached(Property $property, int $landlordUserId): void
    {
        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_PROPERTY_LANDLORD_DETACHED,
            'property',
            (int) $property->id,
            [
                'summary' => 'Landlord #'.$landlordUserId.' detached during offboarding',
                'payload' => ['landlord_user_id' => $landlordUserId],
            ]
        );
    }

    /**
     * @return list<array<int, string|float>>
     */
    public function handoverExportRows(Property $property): array
    {
        $check = $this->statusCheck($property);
        $rows = [
            ['Property', $property->name],
            ['Code', (string) ($property->code ?? '')],
            ['Management status', $property->managementStatusLabel()],
            ['Active leases', (string) ($check['active_leases_count'] ?? 0)],
            ['Open AR (KES)', (string) ($check['open_ar'] ?? 0)],
            ['Open invoices', (string) ($check['open_invoice_count'] ?? 0)],
            ['Landlord payable (KES)', (string) ($check['landlord_payable_balance'] ?? 0)],
            ['Open maintenance', (string) ($check['open_maintenance'] ?? 0)],
            ['Pending notices', (string) ($check['pending_notices'] ?? 0)],
            ['Exported at', now()->toDateTimeString()],
            [],
            ['Lease ID', 'Tenant', 'Units', 'Monthly rent', 'Status'],
        ];

        /** @var PmLease $lease */
        foreach (($check['active_leases'] ?? collect()) as $lease) {
            $rows[] = [
                (string) $lease->id,
                (string) ($lease->pmTenant?->name ?? ''),
                $lease->units->pluck('label')->join(', '),
                (string) $lease->monthly_rent,
                (string) $lease->status,
            ];
        }

        return $rows;
    }

    public function handoverCsvResponse(Property $property): StreamedResponse
    {
        $filename = 'property-handover-'.($property->code ?: $property->id).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($property) {
            $out = fopen('php://output', 'w');
            foreach ($this->handoverExportRows($property) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return list<int>
     */
    private function unitIds(Property $property): array
    {
        return PropertyUnit::query()
            ->where('property_id', $property->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function landlordPayableBalance(Property $property, Collection $landlords): float
    {
        if ($landlords->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($landlords as $landlord) {
            if (! $landlord instanceof User) {
                continue;
            }

            if (Schema::hasTable('pm_landlord_ledger_entries')) {
                $last = DB::table('pm_landlord_ledger_entries')
                    ->where('user_id', $landlord->id)
                    ->where(function ($q) use ($property) {
                        $q->where('property_id', $property->id)->orWhereNull('property_id');
                    })
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
                    ->value('balance_after');

                if ($last !== null) {
                    $total += max(0.0, (float) $last);
                }
            } else {
                $total += max(0.0, LandlordLedger::balance($landlord));
            }
        }

        return $total;
    }
}
