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

        $now = now();
        $permissionId = DB::table('pm_permissions')->where('key', 'utilities.readings.capture')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('pm_permissions')->insertGetId([
                'name' => 'Record meter readings',
                'key' => 'utilities.readings.capture',
                'group' => 'revenue',
                'description' => 'Capture water, electricity, and other meter readings in the field.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_role_permission')) {
            return;
        }

        foreach (['property_manager', 'maintenance_officer'] as $slug) {
            $roleId = DB::table('pm_roles')->where('slug', $slug)->value('id');
            if (! $roleId) {
                continue;
            }
            $exists = DB::table('pm_role_permission')
                ->where('pm_role_id', $roleId)
                ->where('pm_permission_id', $permissionId)
                ->exists();
            if (! $exists) {
                DB::table('pm_role_permission')->insert([
                    'pm_role_id' => $roleId,
                    'pm_permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_permissions')) {
            return;
        }

        $permissionId = DB::table('pm_permissions')->where('key', 'utilities.readings.capture')->value('id');
        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable('pm_role_permission')) {
            DB::table('pm_role_permission')->where('pm_permission_id', $permissionId)->delete();
        }
        DB::table('pm_permissions')->where('id', $permissionId)->delete();
    }
};
