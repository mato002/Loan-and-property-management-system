<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_rent')) {
                $table->decimal('opening_arrears_rent', 14, 2)->default(0)->after('risk_level');
            }
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_utilities')) {
                $table->decimal('opening_arrears_utilities', 14, 2)->default(0)->after('opening_arrears_rent');
            }
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_penalties')) {
                $table->decimal('opening_arrears_penalties', 14, 2)->default(0)->after('opening_arrears_utilities');
            }
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_other')) {
                $table->decimal('opening_arrears_other', 14, 2)->default(0)->after('opening_arrears_penalties');
            }
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_amount')) {
                $table->decimal('opening_arrears_amount', 14, 2)->default(0)->after('opening_arrears_other');
            }
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_as_of')) {
                $table->date('opening_arrears_as_of')->nullable()->after('opening_arrears_amount');
            }
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_notes')) {
                $table->string('opening_arrears_notes', 500)->nullable()->after('opening_arrears_as_of');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_tenants', function (Blueprint $table) {
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_notes')) {
                $table->dropColumn('opening_arrears_notes');
            }
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_as_of')) {
                $table->dropColumn('opening_arrears_as_of');
            }
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_amount')) {
                $table->dropColumn('opening_arrears_amount');
            }
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_other')) {
                $table->dropColumn('opening_arrears_other');
            }
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_penalties')) {
                $table->dropColumn('opening_arrears_penalties');
            }
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_utilities')) {
                $table->dropColumn('opening_arrears_utilities');
            }
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_rent')) {
                $table->dropColumn('opening_arrears_rent');
            }
        });
    }
};

