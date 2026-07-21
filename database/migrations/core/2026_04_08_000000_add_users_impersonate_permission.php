<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_permissions')) {
            return;
        }

        $exists = DB::table('pm_permissions')->where('key', 'users.impersonate')->exists();
        if ($exists) {
            return;
        }

        DB::table('pm_permissions')->insert([
            'name' => 'Impersonate users',
            'key' => 'users.impersonate',
            'group' => 'users',
            'description' => 'Allows an authorized agent to log in as a landlord/tenant for support/testing without knowing their password.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_permissions')) {
            return;
        }

        DB::table('pm_permissions')->where('key', 'users.impersonate')->delete();
    }
};

