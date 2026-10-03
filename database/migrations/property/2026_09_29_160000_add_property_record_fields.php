<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table) {
                if (! Schema::hasColumn('properties', 'acquired_at')) {
                    $table->date('acquired_at')->nullable();
                }
                if (! Schema::hasColumn('properties', 'management_mode')) {
                    $table->string('management_mode', 24)->default('managing');
                }
                if (! Schema::hasColumn('properties', 'lr_number')) {
                    $table->string('lr_number', 64)->nullable();
                }
                if (! Schema::hasColumn('properties', 'category')) {
                    $table->string('category', 64)->nullable();
                }
                if (! Schema::hasColumn('properties', 'property_type')) {
                    $table->string('property_type', 64)->nullable();
                }
                if (! Schema::hasColumn('properties', 'specification')) {
                    $table->string('specification', 64)->nullable();
                }
                if (! Schema::hasColumn('properties', 'storey_type')) {
                    $table->string('storey_type', 64)->nullable();
                }
                if (! Schema::hasColumn('properties', 'floors_count')) {
                    $table->unsignedSmallInteger('floors_count')->nullable();
                }
                if (! Schema::hasColumn('properties', 'country')) {
                    $table->string('country', 64)->nullable();
                }
                if (! Schema::hasColumn('properties', 'estate')) {
                    $table->string('estate', 128)->nullable();
                }
                if (! Schema::hasColumn('properties', 'zone')) {
                    $table->string('zone', 128)->nullable();
                }
                if (! Schema::hasColumn('properties', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('properties', 'contact_info')) {
                    $table->text('contact_info')->nullable();
                }
                if (! Schema::hasColumn('properties', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('properties', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('properties', 'gross_lettable_area')) {
                    $table->decimal('gross_lettable_area', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('properties', 'net_lettable_area')) {
                    $table->decimal('net_lettable_area', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('properties', 'area_unit')) {
                    $table->string('area_unit', 16)->nullable();
                }
                if (! Schema::hasColumn('properties', 'rent_per_measure')) {
                    $table->decimal('rent_per_measure', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('properties', 'statement_balance_cutoff_day')) {
                    $table->unsignedTinyInteger('statement_balance_cutoff_day')->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_notes')) {
                    $table->text('listing_notes')->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_agent_name')) {
                    $table->string('listing_agent_name', 128)->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_contact_email')) {
                    $table->string('listing_contact_email', 255)->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_contact_phone')) {
                    $table->string('listing_contact_phone', 32)->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_min_rent')) {
                    $table->decimal('listing_min_rent', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_max_rent')) {
                    $table->decimal('listing_max_rent', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_min_service_charge')) {
                    $table->decimal('listing_min_service_charge', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('properties', 'listing_max_service_charge')) {
                    $table->decimal('listing_max_service_charge', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('properties', 'exclude_from_fee_summary')) {
                    $table->boolean('exclude_from_fee_summary')->default(false);
                }
                if (! Schema::hasColumn('properties', 'communication_exemptions')) {
                    $table->json('communication_exemptions')->nullable();
                }
            });
        }

        if (Schema::hasTable('pm_landlord_portal_profiles')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_landlord_portal_profiles', 'bank_branch')) {
                    $table->string('bank_branch', 120)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_portal_profiles', 'bank_account_name')) {
                    $table->string('bank_account_name', 120)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table) {
                $columns = [
                    'acquired_at',
                    'management_mode',
                    'lr_number',
                    'category',
                    'property_type',
                    'specification',
                    'storey_type',
                    'floors_count',
                    'country',
                    'estate',
                    'zone',
                    'notes',
                    'contact_info',
                    'latitude',
                    'longitude',
                    'gross_lettable_area',
                    'net_lettable_area',
                    'area_unit',
                    'rent_per_measure',
                    'statement_balance_cutoff_day',
                    'listing_notes',
                    'listing_agent_name',
                    'listing_contact_email',
                    'listing_contact_phone',
                    'listing_min_rent',
                    'listing_max_rent',
                    'listing_min_service_charge',
                    'listing_max_service_charge',
                    'exclude_from_fee_summary',
                    'communication_exemptions',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('properties', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pm_landlord_portal_profiles')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
                foreach (['bank_branch', 'bank_account_name'] as $column) {
                    if (Schema::hasColumn('pm_landlord_portal_profiles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
