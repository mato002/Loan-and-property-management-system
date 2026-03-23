<?php

namespace Database\Seeders;

use App\Models\PmAmenity;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLease;
use App\Models\PmListingApplication;
use App\Models\PmListingLead;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmVendor;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitPublicImage;
use App\Models\User;
use App\Services\Property\LandlordLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PropertyModuleDemoSeeder extends Seeder
{
    /** Minimal valid PNG (1×1) for listing gallery URLs. */
    private static function tinyPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

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

        $landlordCo = User::query()->firstOrCreate(
            ['email' => 'coowner@property.demo'],
            [
                'name' => 'Demo Co-owner',
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

        $property = Property::query()->updateOrCreate(
            ['code' => 'DEMO-001'],
            [
                'name' => 'Sunset Ridge Residences',
                'address_line' => '14 Riverside Drive, Westlands',
                'city' => 'Nairobi',
            ],
        );

        $property->landlords()->sync([
            $landlord->id => ['ownership_percent' => 65.00],
            $landlordCo->id => ['ownership_percent' => 35.00],
        ]);

        $unit1A = PropertyUnit::query()->updateOrCreate(
            ['property_id' => $property->id, 'label' => '1A'],
            $this->withUnitType([
                'bedrooms' => 2,
                'rent_amount' => 52000,
                'status' => PropertyUnit::STATUS_OCCUPIED,
                'vacant_since' => null,
                'public_listing_published' => false,
                'public_listing_description' => null,
            ], PropertyUnit::TYPE_APARTMENT),
        );

        $unit2B = PropertyUnit::query()->updateOrCreate(
            ['property_id' => $property->id, 'label' => '2B'],
            $this->withUnitType([
                'bedrooms' => 0,
                'rent_amount' => 33500,
                'status' => PropertyUnit::STATUS_VACANT,
                'vacant_since' => now()->subDays(38)->toDateString(),
                'public_listing_published' => true,
                'public_listing_description' => <<<'TXT'
Well-planned bedsitter with a kitchenette, built-in storage, and a bright window line. Water is included in the service charge; power is prepaid. Ideal for a single professional — quiet block, biometric access, and backup generator for common areas.

Viewings: weekdays 9am–5pm by appointment.
TXT,
            ], PropertyUnit::TYPE_BEDSITTER),
        );

        $unit3C = PropertyUnit::query()->updateOrCreate(
            ['property_id' => $property->id, 'label' => '3C'],
            $this->withUnitType([
                'bedrooms' => 3,
                'rent_amount' => 78500,
                'status' => PropertyUnit::STATUS_NOTICE,
                'vacant_since' => null,
                'public_listing_published' => false,
                'public_listing_description' => null,
            ], PropertyUnit::TYPE_MAISONETTE),
        );

        $png = self::tinyPng();
        if (Schema::hasTable('property_unit_public_images') && $unit2B->publicImages()->doesntExist()) {
            foreach (['main', 'kitchen', 'bedroom'] as $i => $slug) {
                $path = 'demo-seed/'.$property->id.'/2b-'.$slug.'.png';
                Storage::disk('public')->put($path, $png);
                PropertyUnitPublicImage::query()->create([
                    'property_unit_id' => $unit2B->id,
                    'path' => $path,
                    'sort_order' => $i,
                ]);
            }
        }

        if (Schema::hasTable('pm_amenities') && Schema::hasTable('pm_amenity_unit')) {
            $amenityIds = PmAmenity::query()->orderBy('id')->pluck('id')->all();
            if ($amenityIds !== []) {
                $unit2B->amenities()->sync($amenityIds);
            }
        }

        if (Schema::hasTable('pm_unit_utility_charges')) {
            PmUnitUtilityCharge::query()->updateOrCreate(
                [
                    'property_unit_id' => $unit2B->id,
                    'label' => 'Service charge',
                ],
                [
                    'amount' => 2500,
                    'notes' => 'Estate levy — security, cleaning, generator for common areas.',
                ],
            );
            PmUnitUtilityCharge::query()->updateOrCreate(
                [
                    'property_unit_id' => $unit2B->id,
                    'label' => 'Water (fixed)',
                ],
                [
                    'amount' => 800,
                    'notes' => 'Flat monthly until sub-meter installed.',
                ],
            );
        }

        if (Schema::hasTable('pm_listing_leads')) {
            PmListingLead::query()->updateOrCreate(
                [
                    'name' => 'DEMO Lead — Wanjiku',
                    'property_unit_id' => $unit2B->id,
                ],
                [
                    'phone' => '+254712345678',
                    'email' => 'wanjiku.lead@example.com',
                    'source' => 'Discover / website',
                    'stage' => 'contacted',
                    'notes' => 'Asked about parking and earliest move-in date.',
                ],
            );
        }

        if (Schema::hasTable('pm_listing_applications')) {
            PmListingApplication::query()->updateOrCreate(
                [
                    'applicant_email' => 'applicant.demo@example.com',
                    'property_unit_id' => $unit2B->id,
                ],
                [
                    'applicant_name' => 'Demo Applicant',
                    'applicant_phone' => '+254798765432',
                    'status' => 'received',
                    'notes' => 'Seed application — documents pending.',
                ],
            );
        }

        $pmTenant = PmTenant::query()->updateOrCreate(
            ['email' => 'tenant@property.demo'],
            [
                'user_id' => $tenantUser->id,
                'name' => 'Jamila Otieno',
                'phone' => '+254722111222',
                'national_id' => '12345678',
                'risk_level' => 'normal',
                'notes' => 'Demo tenant — prefers email for invoices; no pets.',
            ],
        );
        if ($pmTenant->user_id === null) {
            $pmTenant->update(['user_id' => $tenantUser->id]);
        }

        $lease = PmLease::query()->firstOrCreate(
            ['pm_tenant_id' => $pmTenant->id],
            [
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'monthly_rent' => 45000,
                'deposit_amount' => 45000,
                'status' => PmLease::STATUS_ACTIVE,
                'terms_summary' => 'Standard residential lease: rent due by the 5th; one-month deposit held in designated account; notice period 30 days; subletting prohibited; minor repairs tenant responsibility.',
            ],
        );
        $lease->update([
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'monthly_rent' => 45000,
            'deposit_amount' => 45000,
            'status' => PmLease::STATUS_ACTIVE,
            'terms_summary' => 'Standard residential lease: rent due by the 5th; one-month deposit held in designated account; notice period 30 days; subletting prohibited; minor repairs tenant responsibility.',
        ]);
        $lease->units()->sync([$unit1A->id]);

        $pmTenantNotice = PmTenant::query()->updateOrCreate(
            ['email' => 'notice.tenant@property.demo'],
            [
                'user_id' => null,
                'name' => 'Brian Mwangi',
                'phone' => '+254733444555',
                'national_id' => '87654321',
                'risk_level' => 'low',
                'notes' => 'Gave notice to vacate end of quarter — unit shown as notice in occupancy.',
            ],
        );

        $leaseNotice = PmLease::query()->firstOrCreate(
            ['pm_tenant_id' => $pmTenantNotice->id],
            [
                'start_date' => now()->subYear()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'monthly_rent' => 72000,
                'deposit_amount' => 72000,
                'status' => PmLease::STATUS_ACTIVE,
                'terms_summary' => 'Family lease — 3 bed; renewal declined; tenant serving notice per clause 8.',
            ],
        );
        $leaseNotice->units()->sync([$unit3C->id]);

        $invoiceRent = PmInvoice::query()->updateOrCreate(
            ['invoice_no' => 'DEMO-000001'],
            [
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unit1A->id,
                'pm_tenant_id' => $pmTenant->id,
                'issue_date' => now()->startOfMonth()->toDateString(),
                'due_date' => now()->startOfMonth()->addDays(4)->toDateString(),
                'amount' => 45000,
                'amount_paid' => 20000,
                'status' => PmInvoice::STATUS_SENT,
                'description' => 'Monthly rent — Unit 1A (January cycle)',
            ],
        );
        $invoiceRent->refreshComputedStatus();

        $invoiceUtils = PmInvoice::query()->updateOrCreate(
            ['invoice_no' => 'DEMO-000002'],
            [
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unit1A->id,
                'pm_tenant_id' => $pmTenant->id,
                'issue_date' => now()->subMonth()->startOfMonth()->toDateString(),
                'due_date' => now()->subMonth()->startOfMonth()->addDays(7)->toDateString(),
                'amount' => 1200,
                'amount_paid' => 1200,
                'status' => PmInvoice::STATUS_SENT,
                'description' => 'Reimbursement — minor repair coordination (plumbing inspection)',
            ],
        );
        $invoiceUtils->refreshComputedStatus();

        $payment = PmPayment::query()->updateOrCreate(
            ['external_ref' => 'DEMO-MPESA-RNT-001'],
            [
                'pm_tenant_id' => $pmTenant->id,
                'channel' => 'mpesa',
                'amount' => 20000,
                'paid_at' => now()->subDays(2),
                'status' => PmPayment::STATUS_COMPLETED,
                'meta' => ['seed' => true, 'phone' => '2547***000'],
            ],
        );

        PmPaymentAllocation::query()->firstOrCreate(
            [
                'pm_payment_id' => $payment->id,
                'pm_invoice_id' => $invoiceRent->id,
            ],
            ['amount' => 20000],
        );

        $vendor = PmVendor::query()->updateOrCreate(
            ['name' => 'Nairobi Flow Plumbing Ltd'],
            [
                'category' => 'Plumbing',
                'phone' => '+254711999000',
                'email' => 'jobs@nairobiflow.example',
                'status' => 'active',
                'rating' => 4.65,
            ],
        );

        $maintRequest = PmMaintenanceRequest::query()->firstOrCreate(
            [
                'property_unit_id' => $unit1A->id,
                'description' => 'DEMO_SEED: Kitchen sink draining slowly; tenant reports gurgling when dishwasher runs.',
            ],
            [
                'reported_by_user_id' => $tenantUser->id,
                'category' => 'Plumbing',
                'urgency' => 'normal',
                'status' => 'open',
            ],
        );

        PmMaintenanceJob::query()->firstOrCreate(
            ['pm_maintenance_request_id' => $maintRequest->id],
            [
                'pm_vendor_id' => $vendor->id,
                'quote_amount' => 5500,
                'status' => 'quoted',
                'notes' => 'Vendor to snake line and inspect trap; photos on file.',
                'completed_at' => null,
            ],
        );

        if (LandlordLedger::balance($landlord) < 1) {
            LandlordLedger::post(
                $landlord,
                PmLandlordLedgerEntry::DIRECTION_CREDIT,
                125000,
                'Demo seed — recognized rent share (65% beneficial)',
                $property,
            );
        }

        if (LandlordLedger::balance($landlordCo) < 1) {
            LandlordLedger::post(
                $landlordCo,
                PmLandlordLedgerEntry::DIRECTION_CREDIT,
                67230.77,
                'Demo seed — recognized rent share (35% beneficial)',
                $property,
            );
        }

        $this->seedPublicListings();
    }

    private function seedPublicListings(): void
    {
        $properties = [
            ['code' => 'PUB-NAI-001', 'name' => 'Skyline Heights', 'address' => '12 Riverside Lane', 'city' => 'Nairobi'],
            ['code' => 'PUB-NAK-001', 'name' => 'Nakuru Grove Apartments', 'address' => '8 Lakeview Road', 'city' => 'Nakuru'],
            ['code' => 'PUB-MSA-001', 'name' => 'Ocean Crest Residences', 'address' => '45 Nyali Avenue', 'city' => 'Mombasa'],
            ['code' => 'PUB-KSM-001', 'name' => 'Sunset Bay Homes', 'address' => '2 Milimani Drive', 'city' => 'Kisumu'],
            ['code' => 'PUB-ELD-001', 'name' => 'Greenfield Court', 'address' => '17 Pioneer Street', 'city' => 'Eldoret'],
        ];

        $units = [
            ['label' => 'A1', 'bedrooms' => 0, 'unit_type' => PropertyUnit::TYPE_STUDIO, 'copy' => 'Modern studio'],
            ['label' => 'A2', 'bedrooms' => 1, 'unit_type' => PropertyUnit::TYPE_APARTMENT, 'copy' => 'Modern 1-bedroom'],
            ['label' => 'A3', 'bedrooms' => 0, 'unit_type' => PropertyUnit::TYPE_SINGLE_ROOM, 'copy' => 'Single room'],
        ];
        $rents = [28000, 36000, 44000, 52000, 61000];

        foreach ($properties as $index => $row) {
            $property = Property::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'address_line' => $row['address'],
                    'city' => $row['city'],
                ],
            );

            foreach ($units as $labelIndex => $unit) {
                $rent = $rents[min($index + $labelIndex, count($rents) - 1)] + ($labelIndex * 2500);
                $beds = $unit['bedrooms'];

                PropertyUnit::query()->updateOrCreate(
                    ['property_id' => $property->id, 'label' => $unit['label']],
                    $this->withUnitType([
                        'bedrooms' => $beds,
                        'rent_amount' => $rent,
                        'status' => PropertyUnit::STATUS_VACANT,
                        'vacant_since' => now()->subDays(10 + ($index * 3) + $labelIndex)->toDateString(),
                        'public_listing_published' => true,
                        'public_listing_description' => sprintf(
                            '%s unit in %s with secure access, reliable utilities, and easy transport links. Book a site visit for immediate move-in.',
                            $unit['copy'],
                            $row['city']
                        ),
                    ], $unit['unit_type']),
                );
            }
        }
    }

    private function withUnitType(array $attributes, string $unitType): array
    {
        if (Schema::hasColumn('property_units', 'unit_type')) {
            $attributes['unit_type'] = $unitType;
        }

        return $attributes;
    }
}
