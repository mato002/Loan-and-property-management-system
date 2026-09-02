<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('property_units')) {
            Schema::table('property_units', function (Blueprint $table) {
                if (! Schema::hasColumn('property_units', 'floor')) {
                    $table->string('floor', 32)->nullable()->after('bedrooms');
                }
                if (! Schema::hasColumn('property_units', 'market_rent')) {
                    $table->decimal('market_rent', 14, 2)->nullable()->after('rent_amount');
                }
                if (! Schema::hasColumn('property_units', 'furnished')) {
                    $table->boolean('furnished')->default(false)->after('market_rent');
                }
                if (! Schema::hasColumn('property_units', 'available_from')) {
                    $table->date('available_from')->nullable()->after('vacant_since');
                }
                if (! Schema::hasColumn('property_units', 'legacy_area')) {
                    $table->decimal('legacy_area', 14, 2)->nullable()->after('available_from');
                }
            });
        }

        if (Schema::hasTable('pm_leases')) {
            Schema::table('pm_leases', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_leases', 'lease_variation_type')) {
                    $table->string('lease_variation_type', 64)->nullable()->after('status');
                }
                if (! Schema::hasColumn('pm_leases', 'lease_period_days')) {
                    $table->unsignedSmallInteger('lease_period_days')->nullable()->after('lease_variation_type');
                }
                if (! Schema::hasColumn('pm_leases', 'days_to_expire')) {
                    $table->unsignedSmallInteger('days_to_expire')->nullable()->after('lease_period_days');
                }
                if (! Schema::hasColumn('pm_leases', 'escalation_review_start')) {
                    $table->date('escalation_review_start')->nullable()->after('days_to_expire');
                }
            });
        }

        if (Schema::hasTable('pm_landlord_portal_profiles')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_landlord_portal_profiles', 'address_line')) {
                    $table->string('address_line')->nullable()->after('kra_pin');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('property_units')) {
            Schema::table('property_units', function (Blueprint $table) {
                foreach (['floor', 'market_rent', 'furnished', 'available_from', 'legacy_area'] as $column) {
                    if (Schema::hasColumn('property_units', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pm_leases')) {
            Schema::table('pm_leases', function (Blueprint $table) {
                foreach (['lease_variation_type', 'lease_period_days', 'days_to_expire', 'escalation_review_start'] as $column) {
                    if (Schema::hasColumn('pm_leases', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pm_landlord_portal_profiles')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('pm_landlord_portal_profiles', 'address_line')) {
                    $table->dropColumn('address_line');
                }
            });
        }
    }
};
