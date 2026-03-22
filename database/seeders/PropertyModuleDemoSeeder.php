<?php

namespace Database\Seeders;

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\LandlordLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PropertyModuleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        User::query()->firstOrCreate(
            ['email' => 'agent@property.demo'],
            [
                'name' => 'Demo Agent',
                'password' => $password,
                'email_verified_at' => now(),
                'property_portal_role' => 'agent',
            ],
        );

        $landlord = User::query()->firstOrCreate(
            ['email' => 'landlord@property.demo'],
            [
                'name' => 'Demo Landlord',
                'password' => $password,
                'email_verified_at' => now(),
                'property_portal_role' => 'landlord',
            ],
        );

        $tenantUser = User::query()->firstOrCreate(
            ['email' => 'tenant@property.demo'],
            [
                'name' => 'Demo Tenant',
                'password' => $password,
                'email_verified_at' => now(),
                'property_portal_role' => 'tenant',
            ],
        );

        $property = Property::query()->firstOrCreate(
            ['code' => 'DEMO-001'],
            [
                'name' => 'Demo Apartments',
                'address_line' => 'Nairobi',
                'city' => 'Nairobi',
            ],
        );

        $property->landlords()->syncWithoutDetaching([
            $landlord->id => ['ownership_percent' => 100],
        ]);

        $unit = PropertyUnit::query()->firstOrCreate(
            ['property_id' => $property->id, 'label' => '1A'],
            [
                'bedrooms' => 2,
                'rent_amount' => 45000,
                'status' => PropertyUnit::STATUS_VACANT,
            ],
        );

        $pmTenant = PmTenant::query()->firstOrCreate(
            ['email' => 'tenant@property.demo'],
            [
                'user_id' => $tenantUser->id,
                'name' => 'Demo Tenant',
                'phone' => '+254700000000',
                'risk_level' => 'normal',
            ],
        );

        if ($pmTenant->user_id === null) {
            $pmTenant->update(['user_id' => $tenantUser->id]);
        }

        $lease = PmLease::query()->where('pm_tenant_id', $pmTenant->id)->first();
        if ($lease === null) {
            $lease = PmLease::query()->create([
                'pm_tenant_id' => $pmTenant->id,
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'monthly_rent' => 45000,
                'deposit_amount' => 45000,
                'status' => PmLease::STATUS_ACTIVE,
                'terms_summary' => 'Demo lease',
            ]);
            $lease->units()->sync([$unit->id]);
        } else {
            $lease->units()->syncWithoutDetaching([$unit->id]);
        }

        $unit->update([
            'status' => PropertyUnit::STATUS_OCCUPIED,
            'vacant_since' => null,
        ]);

        $invoice = PmInvoice::query()->firstOrCreate(
            ['invoice_no' => 'DEMO-000001'],
            [
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unit->id,
                'pm_tenant_id' => $pmTenant->id,
                'issue_date' => now()->startOfMonth()->toDateString(),
                'due_date' => now()->startOfMonth()->addDays(4)->toDateString(),
                'amount' => 45000,
                'amount_paid' => 0,
                'status' => PmInvoice::STATUS_SENT,
                'description' => 'Monthly rent',
            ],
        );
        $invoice->refreshComputedStatus();

        if (LandlordLedger::balance($landlord) < 1) {
            LandlordLedger::post(
                $landlord,
                PmLandlordLedgerEntry::DIRECTION_CREDIT,
                125000,
                'Demo seed — recognized rent share',
                $property,
            );
        }
    }
}
