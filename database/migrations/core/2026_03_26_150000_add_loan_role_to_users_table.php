<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'loan_role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('loan_role', 32)->nullable()->after('property_portal_role');
            $table->index('loan_role', 'users_loan_role_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'loan_role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_loan_role_idx');
            $table->dropColumn('loan_role');
        });
    }
};

