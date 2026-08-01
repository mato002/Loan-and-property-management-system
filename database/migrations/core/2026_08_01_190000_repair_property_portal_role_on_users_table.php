<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'property_portal_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('property_portal_role', 32)->nullable()->after('password');
            });
        }

        if (! Schema::hasColumn('users', 'property_portal_role')) {
            return;
        }

        foreach (['agent', 'landlord', 'tenant'] as $role) {
            $query = DB::table('users')->whereNull('property_portal_role');

            $query->where(function ($q) use ($role): void {
                if (Schema::hasColumn('users', 'portal_context')) {
                    $q->orWhere('portal_context', $role);
                }
                if (Schema::hasColumn('users', 'user_type')) {
                    $q->orWhere('user_type', $role);
                }
            });

            $query->update(['property_portal_role' => $role]);
        }

        if (Schema::hasTable('property_landlord')) {
            $landlordIds = DB::table('property_landlord')
                ->distinct()
                ->pluck('user_id')
                ->filter()
                ->all();

            if ($landlordIds !== []) {
                DB::table('users')
                    ->whereIn('id', $landlordIds)
                    ->whereNull('property_portal_role')
                    ->update(['property_portal_role' => 'landlord']);
            }
        }

        if (Schema::hasTable('pm_tenants') && Schema::hasColumn('pm_tenants', 'user_id')) {
            $tenantUserIds = DB::table('pm_tenants')
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id')
                ->filter()
                ->all();

            if ($tenantUserIds !== []) {
                DB::table('users')
                    ->whereIn('id', $tenantUserIds)
                    ->whereNull('property_portal_role')
                    ->update(['property_portal_role' => 'tenant']);
            }
        }

        $agentIds = collect();

        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'agent_user_id')) {
            $agentIds = $agentIds->merge(
                DB::table('properties')->whereNotNull('agent_user_id')->distinct()->pluck('agent_user_id')
            );
        }

        if (Schema::hasTable('pm_tenants') && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $agentIds = $agentIds->merge(
                DB::table('pm_tenants')->whereNotNull('agent_user_id')->distinct()->pluck('agent_user_id')
            );
        }

        if (Schema::hasTable('pm_vendors') && Schema::hasColumn('pm_vendors', 'agent_user_id')) {
            $agentIds = $agentIds->merge(
                DB::table('pm_vendors')->whereNotNull('agent_user_id')->distinct()->pluck('agent_user_id')
            );
        }

        if (Schema::hasTable('pm_user_role')) {
            $agentIds = $agentIds->merge(
                DB::table('pm_user_role')->distinct()->pluck('user_id')
            );
        }

        $agentIds = $agentIds->filter()->unique()->values()->all();

        if ($agentIds !== []) {
            DB::table('users')
                ->whereIn('id', $agentIds)
                ->whereNull('property_portal_role')
                ->update(['property_portal_role' => 'agent']);
        }

        $demoRoles = [
            'agent@property.demo' => 'agent',
            'landlord@property.demo' => 'landlord',
            'coowner@property.demo' => 'landlord',
            'tenant@property.demo' => 'tenant',
        ];

        foreach ($demoRoles as $email => $role) {
            DB::table('users')
                ->whereNull('property_portal_role')
                ->where('email', $email)
                ->update(['property_portal_role' => $role]);
        }
    }

    public function down(): void
    {
        // Repair migration — no down.
    }
};
