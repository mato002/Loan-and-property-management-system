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
            if (! Schema::hasColumn('loan_products', 'payment_interval_days')) {
                $table->unsignedSmallInteger('payment_interval_days')->nullable()->after('default_interest_rate_period');
            }
            if (! Schema::hasColumn('loan_products', 'total_interest_amount')) {
                $table->decimal('total_interest_amount', 15, 2)->nullable()->after('payment_interval_days');
            }
            if (! Schema::hasColumn('loan_products', 'interest_duration_value')) {
                $table->unsignedSmallInteger('interest_duration_value')->nullable()->after('total_interest_amount');
            }
            if (! Schema::hasColumn('loan_products', 'interest_type')) {
                $table->string('interest_type', 32)->nullable()->after('interest_duration_value');
            }
            if (! Schema::hasColumn('loan_products', 'min_loan_amount')) {
                $table->decimal('min_loan_amount', 15, 2)->nullable()->after('interest_type');
            }
            if (! Schema::hasColumn('loan_products', 'max_loan_amount')) {
                $table->decimal('max_loan_amount', 15, 2)->nullable()->after('min_loan_amount');
            }
            if (! Schema::hasColumn('loan_products', 'arrears_penalty_scope')) {
                $table->string('arrears_penalty_scope', 32)->nullable()->after('max_loan_amount');
            }
            if (! Schema::hasColumn('loan_products', 'penalty_amount')) {
                $table->decimal('penalty_amount', 15, 2)->nullable()->after('arrears_penalty_scope');
            }
            if (! Schema::hasColumn('loan_products', 'rollover_fees')) {
                $table->decimal('rollover_fees', 15, 2)->nullable()->after('penalty_amount');
            }
            if (! Schema::hasColumn('loan_products', 'loan_offset_fees')) {
                $table->decimal('loan_offset_fees', 15, 2)->nullable()->after('rollover_fees');
            }
            if (! Schema::hasColumn('loan_products', 'repay_waiver_days')) {
                $table->unsignedSmallInteger('repay_waiver_days')->nullable()->after('loan_offset_fees');
            }
            if (! Schema::hasColumn('loan_products', 'client_application_scope')) {
                $table->string('client_application_scope', 32)->nullable()->after('repay_waiver_days');
            }
            if (! Schema::hasColumn('loan_products', 'installment_display_mode')) {
                $table->string('installment_display_mode', 32)->nullable()->after('client_application_scope');
            }
            if (! Schema::hasColumn('loan_products', 'exempt_from_checkoffs')) {
                $table->boolean('exempt_from_checkoffs')->default(false)->after('installment_display_mode');
            }
            if (! Schema::hasColumn('loan_products', 'cluster_name')) {
                $table->string('cluster_name', 160)->nullable()->after('exempt_from_checkoffs');
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
                'cluster_name',
                'exempt_from_checkoffs',
                'installment_display_mode',
                'client_application_scope',
                'repay_waiver_days',
                'loan_offset_fees',
                'rollover_fees',
                'penalty_amount',
                'arrears_penalty_scope',
                'max_loan_amount',
                'min_loan_amount',
                'interest_type',
                'interest_duration_value',
                'total_interest_amount',
                'payment_interval_days',
            ] as $column) {
                if (Schema::hasColumn('loan_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

