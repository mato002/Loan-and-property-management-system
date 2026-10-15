<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_penalty_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('pm_penalty_rules', 'property_id')) {
                $table->foreignId('property_id')->nullable()->after('scope')->constrained('properties')->nullOnDelete();
            }
            if (! Schema::hasColumn('pm_penalty_rules', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('property_id')->constrained('property_units')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_penalty_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('pm_penalty_rules', 'unit_id')) {
                $table->dropConstrainedForeignId('unit_id');
            }
            if (Schema::hasColumn('pm_penalty_rules', 'property_id')) {
                $table->dropConstrainedForeignId('property_id');
            }
        });
    }
};
