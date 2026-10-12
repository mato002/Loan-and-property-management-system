<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_bank_statement_lines')) {
            return;
        }

        Schema::table('pm_bank_statement_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('pm_bank_statement_lines', 'paid_to_employee_id')) {
                $table->unsignedBigInteger('paid_to_employee_id')->nullable()->after('paid_to_landlord_id');
            }
            if (! Schema::hasColumn('pm_bank_statement_lines', 'paid_to_vendor_id')) {
                $table->unsignedBigInteger('paid_to_vendor_id')->nullable()->after('paid_to_employee_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_bank_statement_lines')) {
            return;
        }

        Schema::table('pm_bank_statement_lines', function (Blueprint $table): void {
            foreach (['paid_to_vendor_id', 'paid_to_employee_id'] as $column) {
                if (Schema::hasColumn('pm_bank_statement_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
