<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_tenants', 'opening_arrears_items')) {
                $table->json('opening_arrears_items')->nullable()->after('opening_arrears_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_tenants', function (Blueprint $table) {
            if (Schema::hasColumn('pm_tenants', 'opening_arrears_items')) {
                $table->dropColumn('opening_arrears_items');
            }
        });
    }
};

