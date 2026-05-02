<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_products')) {
            return;
        }

        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')) {
                $table->timestamp('loan_form_setup_completed_at')->nullable();
            }
        });

        if (Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')) {
            DB::table('loan_products')->whereNull('loan_form_setup_completed_at')->update([
                'loan_form_setup_completed_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_products')) {
            return;
        }

        Schema::table('loan_products', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')) {
                $table->dropColumn('loan_form_setup_completed_at');
            }
        });
    }
};
