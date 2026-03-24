<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_roles')) {
            Schema::create('pm_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->string('portal_scope', 24)->default('agent'); // agent|landlord|tenant|any
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_permissions')) {
            Schema::create('pm_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('key', 120)->unique();
                $table->string('group', 60)->default('general');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_role_permission')) {
            Schema::create('pm_role_permission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pm_role_id')->constrained('pm_roles')->cascadeOnDelete();
                $table->foreignId('pm_permission_id')->constrained('pm_permissions')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['pm_role_id', 'pm_permission_id'], 'pm_role_perm_uq');
            });
        }

        if (! Schema::hasTable('pm_user_role')) {
            Schema::create('pm_user_role', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pm_role_id')->constrained('pm_roles')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'pm_role_id'], 'pm_user_role_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_user_role');
        Schema::dropIfExists('pm_role_permission');
        Schema::dropIfExists('pm_permissions');
        Schema::dropIfExists('pm_roles');
    }
};

