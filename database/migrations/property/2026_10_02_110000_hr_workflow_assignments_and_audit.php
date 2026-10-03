<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_property_assignments')) {
            Schema::create('employee_property_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('property_id');
                $table->timestamps();

                $table->unique(['employee_id', 'property_id']);
                $table->index('property_id');
            });
        }

        if (Schema::hasTable('pm_maintenance_requests') && ! Schema::hasColumn('pm_maintenance_requests', 'assigned_user_id')) {
            Schema::table('pm_maintenance_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('assigned_user_id')->nullable()->after('reported_by_user_id');
                $table->index('assigned_user_id');
            });
        }

        if (Schema::hasTable('pm_activity_logs') && ! Schema::hasColumn('pm_activity_logs', 'employee_id')) {
            Schema::table('pm_activity_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('actor_user_id');
                $table->index('employee_id');
            });
        }
        if (Schema::hasTable('pm_activity_logs') && ! Schema::hasColumn('pm_activity_logs', 'employee_role')) {
            Schema::table('pm_activity_logs', function (Blueprint $table) {
                $table->string('employee_role', 191)->nullable()->after('employee_id');
            });
        }

        $this->seedMaintenanceWorkflowPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_activity_logs') && Schema::hasColumn('pm_activity_logs', 'employee_role')) {
            Schema::table('pm_activity_logs', function (Blueprint $table) {
                $table->dropColumn('employee_role');
            });
        }
        if (Schema::hasTable('pm_activity_logs') && Schema::hasColumn('pm_activity_logs', 'employee_id')) {
            Schema::table('pm_activity_logs', function (Blueprint $table) {
                $table->dropIndex(['employee_id']);
                $table->dropColumn('employee_id');
            });
        }
        if (Schema::hasTable('pm_maintenance_requests') && Schema::hasColumn('pm_maintenance_requests', 'assigned_user_id')) {
            Schema::table('pm_maintenance_requests', function (Blueprint $table) {
                $table->dropIndex(['assigned_user_id']);
                $table->dropColumn('assigned_user_id');
            });
        }
        Schema::dropIfExists('employee_property_assignments');
    }

    private function seedMaintenanceWorkflowPermissions(): void
    {
        if (! Schema::hasTable('pm_permissions')) {
            return;
        }

        $now = now();
        $keys = [
            'maintenance.resolve' => ['name' => 'Resolve maintenance', 'group' => 'maintenance', 'description' => 'Receives maintenance tickets for assigned properties.'],
            'maintenance.approve_high_value' => ['name' => 'Approve high-value maintenance', 'group' => 'maintenance', 'description' => 'Approves repair estimates above the workflow threshold.'],
        ];
        $ids = [];
        foreach ($keys as $key => $meta) {
            $id = DB::table('pm_permissions')->where('key', $key)->value('id');
            if (! $id) {
                $id = DB::table('pm_permissions')->insertGetId([
                    'name' => $meta['name'],
                    'key' => $key,
                    'group' => $meta['group'],
                    'description' => $meta['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $ids[$key] = (int) $id;
        }

        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_role_permission')) {
            return;
        }

        $roleKeys = [
            'maintenance_officer' => ['maintenance.resolve'],
            'property_manager' => ['maintenance.resolve', 'maintenance.approve_high_value'],
        ];
        foreach ($roleKeys as $slug => $permKeys) {
            $roleId = DB::table('pm_roles')->where('slug', $slug)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach ($permKeys as $permKey) {
                $permId = $ids[$permKey] ?? null;
                if (! $permId) {
                    continue;
                }
                $exists = DB::table('pm_role_permission')
                    ->where('pm_role_id', $roleId)
                    ->where('pm_permission_id', $permId)
                    ->exists();
                if (! $exists) {
                    DB::table('pm_role_permission')->insert([
                        'pm_role_id' => $roleId,
                        'pm_permission_id' => $permId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
