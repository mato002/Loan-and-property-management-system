<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_roles')) {
            Schema::create('loan_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->string('base_role', 32)->default('user');
                $table->string('description', 500)->nullable();
                $table->json('permissions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['is_active', 'base_role']);
            });
        }

        if (! Schema::hasTable('loan_user_role')) {
            Schema::create('loan_user_role', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_role_id')->constrained('loan_roles')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_user_role');
        Schema::dropIfExists('loan_roles');
    }
};
