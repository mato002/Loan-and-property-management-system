<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_landlord')) {
            return;
        }

        Schema::table('property_landlord', function (Blueprint $table) {
            if (! Schema::hasColumn('property_landlord', 'settlement_basis')) {
                $table->string('settlement_basis', 24)->default('collected')->after('agreed_pay_notes');
            }
            if (! Schema::hasColumn('property_landlord', 'commission_basis')) {
                $table->string('commission_basis', 24)->default('all_collected')->after('settlement_basis');
            }
            if (! Schema::hasColumn('property_landlord', 'provider_cost_payer')) {
                $table->string('provider_cost_payer', 24)->default('landlord')->after('commission_basis');
            }
            if (! Schema::hasColumn('property_landlord', 'advances_allowed')) {
                $table->boolean('advances_allowed')->default(true)->after('provider_cost_payer');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('property_landlord')) {
            return;
        }

        Schema::table('property_landlord', function (Blueprint $table) {
            foreach (['settlement_basis', 'commission_basis', 'provider_cost_payer', 'advances_allowed'] as $column) {
                if (Schema::hasColumn('property_landlord', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
