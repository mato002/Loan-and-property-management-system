<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_leases') || ! Schema::hasColumn('pm_leases', 'end_date')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table) {
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_leases') || ! Schema::hasColumn('pm_leases', 'end_date')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table) {
            $table->date('end_date')->nullable(false)->change();
        });
    }
};

