<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'property_portal_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('property_portal_role');
            });
        }
    }
};
