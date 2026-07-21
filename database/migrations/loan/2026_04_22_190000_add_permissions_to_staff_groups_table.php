<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_groups')) {
            return;
        }

        Schema::table('staff_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_groups', 'permissions')) {
                $table->json('permissions')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_groups')) {
            return;
        }

        Schema::table('staff_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_groups', 'permissions')) {
                $table->dropColumn('permissions');
            }
        });
    }
};

