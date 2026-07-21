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
            if (! Schema::hasColumn('loan_products', 'default_interest_rate')) {
                $table->decimal('default_interest_rate', 8, 4)->nullable()->after('description');
            }
            if (! Schema::hasColumn('loan_products', 'default_term_months')) {
                $table->unsignedInteger('default_term_months')->nullable()->after('default_interest_rate');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_products')) {
            return;
        }

        Schema::table('loan_products', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_products', 'default_term_months')) {
                $table->dropColumn('default_term_months');
            }
            if (Schema::hasColumn('loan_products', 'default_interest_rate')) {
                $table->dropColumn('default_interest_rate');
            }
        });
    }
};
