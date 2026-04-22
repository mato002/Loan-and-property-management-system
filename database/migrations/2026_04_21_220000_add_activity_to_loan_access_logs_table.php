<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_access_logs')) {
            return;
        }

        Schema::table('loan_access_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_access_logs', 'activity')) {
                $table->string('activity', 500)->nullable()->after('path');
                $table->index('activity');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_access_logs')) {
            return;
        }

        Schema::table('loan_access_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_access_logs', 'activity')) {
                $table->dropIndex(['activity']);
                $table->dropColumn('activity');
            }
        });
    }
};

