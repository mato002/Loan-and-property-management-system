<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_user_permission')) {
            Schema::create('pm_user_permission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pm_permission_id')->constrained('pm_permissions')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'pm_permission_id'], 'pm_user_perm_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_user_permission');
    }
};

