<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_leases')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table): void {
            if (! Schema::hasColumn('pm_leases', 'opening_arrears_manual_total')) {
                $table->decimal('opening_arrears_manual_total', 12, 2)->nullable()->after('opening_arrears');
            }
            if (! Schema::hasColumn('pm_leases', 'opening_arrears_as_of_date')) {
                $table->date('opening_arrears_as_of_date')->nullable()->after('opening_arrears_manual_total');
            }
            if (! Schema::hasColumn('pm_leases', 'opening_arrears_note')) {
                $table->string('opening_arrears_note', 500)->nullable()->after('opening_arrears_as_of_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_leases')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table): void {
            if (Schema::hasColumn('pm_leases', 'opening_arrears_note')) {
                $table->dropColumn('opening_arrears_note');
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_as_of_date')) {
                $table->dropColumn('opening_arrears_as_of_date');
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_manual_total')) {
                $table->dropColumn('opening_arrears_manual_total');
            }
        });
    }
};
