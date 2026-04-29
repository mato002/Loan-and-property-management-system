<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_leases') || Schema::hasColumn('pm_leases', 'opening_arrears')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table): void {
            $table->json('opening_arrears')->nullable()->after('additional_deposits');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_leases') || ! Schema::hasColumn('pm_leases', 'opening_arrears')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table): void {
            $table->dropColumn('opening_arrears');
        });
    }
};
