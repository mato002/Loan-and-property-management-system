<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('property_landlord')) {
            Schema::table('property_landlord', function (Blueprint $table) {
                if (! Schema::hasColumn('property_landlord', 'agreed_pay_day')) {
                    $table->unsignedTinyInteger('agreed_pay_day')->nullable()->after('ownership_percent');
                }
                if (! Schema::hasColumn('property_landlord', 'agreed_pay_notes')) {
                    $table->string('agreed_pay_notes', 255)->nullable()->after('agreed_pay_day');
                }
            });
        }

        if (Schema::hasTable('pm_landlord_payout_items')) {
            Schema::table('pm_landlord_payout_items', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_landlord_payout_items', 'agreed_pay_date')) {
                    $table->date('agreed_pay_date')->nullable()->after('period_month');
                }
                if (! Schema::hasColumn('pm_landlord_payout_items', 'advance_status')) {
                    $table->string('advance_status', 32)->nullable()->after('agreed_pay_date');
                }
                if (! Schema::hasColumn('pm_landlord_payout_items', 'payment_reference')) {
                    $table->string('payment_reference', 128)->nullable()->after('advance_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('property_landlord')) {
            Schema::table('property_landlord', function (Blueprint $table) {
                foreach (['agreed_pay_day', 'agreed_pay_notes'] as $column) {
                    if (Schema::hasColumn('property_landlord', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pm_landlord_payout_items')) {
            Schema::table('pm_landlord_payout_items', function (Blueprint $table) {
                foreach (['agreed_pay_date', 'advance_status', 'payment_reference'] as $column) {
                    if (Schema::hasColumn('pm_landlord_payout_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
