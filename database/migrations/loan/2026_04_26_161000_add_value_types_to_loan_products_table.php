<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_products')) {
            return;
        }

        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'default_interest_rate_type')) {
                $table->string('default_interest_rate_type', 16)->default('percent')->after('default_interest_rate');
            }
            if (! Schema::hasColumn('loan_products', 'penalty_amount_type')) {
                $table->string('penalty_amount_type', 16)->default('fixed')->after('penalty_amount');
            }
            if (! Schema::hasColumn('loan_products', 'rollover_fees_type')) {
                $table->string('rollover_fees_type', 16)->default('fixed')->after('rollover_fees');
            }
            if (! Schema::hasColumn('loan_products', 'loan_offset_fees_type')) {
                $table->string('loan_offset_fees_type', 16)->default('fixed')->after('loan_offset_fees');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_products')) {
            return;
        }

        Schema::table('loan_products', function (Blueprint $table): void {
            foreach ([
                'loan_offset_fees_type',
                'rollover_fees_type',
                'penalty_amount_type',
                'default_interest_rate_type',
            ] as $column) {
                if (Schema::hasColumn('loan_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
