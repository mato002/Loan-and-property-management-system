<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_salary_advances')) {
            return;
        }

        Schema::table('accounting_salary_advances', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_salary_advances', 'form_meta')) {
                $table->json('form_meta')->nullable()->after('settled_on');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_salary_advances')) {
            return;
        }

        Schema::table('accounting_salary_advances', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_salary_advances', 'form_meta')) {
                $table->dropColumn('form_meta');
            }
        });
    }
};
