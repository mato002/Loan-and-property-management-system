<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_penalty_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_penalty_rules', 'compounding_mode')) {
                $table->string('compounding_mode', 32)->default('simple')->after('formula');
            }
            if (! Schema::hasColumn('pm_penalty_rules', 'cumulative_cap')) {
                $table->decimal('cumulative_cap', 14, 2)->nullable()->after('cap');
            }
        });

        Schema::table('pm_invoice_penalty_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_invoice_penalty_applications', 'base_amount')) {
                $table->decimal('base_amount', 14, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('pm_invoice_penalty_applications', 'compounding_mode')) {
                $table->string('compounding_mode', 32)->nullable()->after('base_amount');
            }
            if (! Schema::hasColumn('pm_invoice_penalty_applications', 'days_overdue')) {
                $table->unsignedInteger('days_overdue')->nullable()->after('compounding_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_penalty_rules', function (Blueprint $table) {
            foreach (['compounding_mode', 'cumulative_cap'] as $column) {
                if (Schema::hasColumn('pm_penalty_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('pm_invoice_penalty_applications', function (Blueprint $table) {
            foreach (['base_amount', 'compounding_mode', 'days_overdue'] as $column) {
                if (Schema::hasColumn('pm_invoice_penalty_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
