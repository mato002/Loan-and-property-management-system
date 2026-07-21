<?php

namespace App\Services\Property;

use App\Models\Property;
use Illuminate\Validation\ValidationException;

final class PropertyManagementGuardService
{
    public function status(Property $property): string
    {
        if (! $this->hasManagementStatusColumn()) {
            return Property::MANAGEMENT_ACTIVE;
        }

        return (string) ($property->management_status ?? Property::MANAGEMENT_ACTIVE);
    }

    public function isActive(Property $property): bool
    {
        return $this->status($property) === Property::MANAGEMENT_ACTIVE;
    }

    public function isOffboarding(Property $property): bool
    {
        return $this->status($property) === Property::MANAGEMENT_OFFBOARDING;
    }

    public function isReadOnly(Property $property): bool
    {
        return in_array($this->status($property), Property::READ_ONLY_MANAGEMENT_STATUSES, true);
    }

    public function isNonOperational(Property $property): bool
    {
        return in_array($this->status($property), Property::NON_OPERATIONAL_MANAGEMENT_STATUSES, true);
    }

    public function allowsRentBilling(Property $property): bool
    {
        $status = $this->status($property);

        if (in_array($status, [Property::MANAGEMENT_ARCHIVED, Property::MANAGEMENT_ENDED], true)) {
            return false;
        }

        return true;
    }

    public function allowsWaterBilling(Property $property): bool
    {
        return ! in_array($this->status($property), [Property::MANAGEMENT_ARCHIVED, Property::MANAGEMENT_ENDED], true);
    }

    public function allowsRentReminders(Property $property): bool
    {
        return $this->status($property) !== Property::MANAGEMENT_ARCHIVED;
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreateLease(Property $property): void
    {
        if ($this->isReadOnly($property)) {
            throw ValidationException::withMessages([
                'property' => 'Cannot create leases on a property that is archived or has ended management.',
            ]);
        }

        if ($this->isOffboarding($property)) {
            throw ValidationException::withMessages([
                'property' => 'Cannot create new leases while this property is offboarding.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreateTenantForUnits(array $unitIds): void
    {
        if ($unitIds === []) {
            return;
        }

        $properties = Property::query()
            ->whereIn('id', function ($sub) use ($unitIds) {
                $sub->select('property_id')->from('property_units')->whereIn('id', $unitIds);
            })
            ->get();

        foreach ($properties as $property) {
            if ($this->isReadOnly($property) || $this->isOffboarding($property)) {
                throw ValidationException::withMessages([
                    'property' => 'Cannot assign tenants on a property that is offboarding or read-only.',
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreateInvoice(Property $property): void
    {
        if ($this->isReadOnly($property)) {
            throw ValidationException::withMessages([
                'property' => 'Cannot issue new invoices on a read-only property.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreateMaintenance(Property $property): void
    {
        if ($this->isReadOnly($property)) {
            throw ValidationException::withMessages([
                'property' => 'Cannot log new maintenance on an archived or ended-management property.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSetupUtility(Property $property): void
    {
        if ($this->isReadOnly($property) || $this->isOffboarding($property)) {
            throw ValidationException::withMessages([
                'property' => 'Cannot configure utilities while this property is offboarding or read-only.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanAddUnit(Property $property): void
    {
        if ($this->isReadOnly($property) || $this->isOffboarding($property)) {
            throw ValidationException::withMessages([
                'property' => 'Cannot add units while this property is offboarding or read-only.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanDestroyUnit(Property $property): void
    {
        if ($this->isNonOperational($property)) {
            throw ValidationException::withMessages([
                'unit' => 'This unit belongs to an archived property. Units are kept for lease and billing history — restore the property from offboarding if you need it active again.',
            ]);
        }

        if ($this->isOffboarding($property)) {
            throw ValidationException::withMessages([
                'unit' => 'Cannot delete units while the property is offboarding. Complete or restore offboarding first.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanMutatePropertySettings(Property $property): void
    {
        if ($this->isReadOnly($property)) {
            throw ValidationException::withMessages([
                'property' => 'This property is read-only. Restore it before changing settings.',
            ]);
        }
    }

    public function propertyForUnitId(int $unitId): ?Property
    {
        if ($unitId <= 0) {
            return null;
        }

        return Property::query()
            ->whereIn('id', function ($sub) use ($unitId) {
                $sub->select('property_id')->from('property_units')->where('id', $unitId);
            })
            ->first();
    }

    public function propertyForUnitIds(array $unitIds): ?Property
    {
        $unitIds = array_values(array_filter(array_map('intval', $unitIds)));
        if ($unitIds === []) {
            return null;
        }

        return Property::query()
            ->whereIn('id', function ($sub) use ($unitIds) {
                $sub->select('property_id')->from('property_units')->whereIn('id', $unitIds);
            })
            ->first();
    }

    private function hasManagementStatusColumn(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('properties', 'management_status');
    }
}
