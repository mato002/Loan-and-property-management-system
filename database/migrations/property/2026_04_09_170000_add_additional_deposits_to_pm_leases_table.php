<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pm_leases', 'additional_deposits')) {
            Schema::table('pm_leases', function (Blueprint $table) {
                $table->json('additional_deposits')->nullable()->after('deposit_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pm_leases', 'additional_deposits')) {
            Schema::table('pm_leases', function (Blueprint $table) {
                $table->dropColumn('additional_deposits');
            });
        }
    }
};
