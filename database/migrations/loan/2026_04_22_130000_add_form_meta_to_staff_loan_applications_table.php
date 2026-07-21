<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_loan_applications')) {
            return;
        }

        Schema::table('staff_loan_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_loan_applications', 'form_meta')) {
                $table->json('form_meta')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_loan_applications')) {
            return;
        }

        Schema::table('staff_loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('staff_loan_applications', 'form_meta')) {
                $table->dropColumn('form_meta');
            }
        });
    }
};
