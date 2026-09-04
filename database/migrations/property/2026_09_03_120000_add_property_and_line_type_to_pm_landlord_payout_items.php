<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_landlord_payout_items')) {
            return;
        }

        Schema::table('pm_landlord_payout_items', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_landlord_payout_items', 'property_id')) {
                $table->unsignedBigInteger('property_id')->nullable()->after('landlord_id');
                $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            }
            if (! Schema::hasColumn('pm_landlord_payout_items', 'line_type')) {
                $table->string('line_type', 32)->default('remittance')->after('amount');
            }
            if (! Schema::hasColumn('pm_landlord_payout_items', 'description')) {
                $table->string('description', 255)->nullable()->after('line_type');
            }
            if (! Schema::hasColumn('pm_landlord_payout_items', 'period_month')) {
                $table->char('period_month', 7)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_landlord_payout_items')) {
            return;
        }

        Schema::table('pm_landlord_payout_items', function (Blueprint $table) {
            if (Schema::hasColumn('pm_landlord_payout_items', 'property_id')) {
                $table->dropForeign(['property_id']);
                $table->dropColumn('property_id');
            }
            foreach (['line_type', 'description', 'period_month'] as $column) {
                if (Schema::hasColumn('pm_landlord_payout_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
