<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_module_accesses')) {
            return;
        }

        Schema::create('user_module_accesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // module: property or loan
            $table->string('module', 32);

            // status: approved, pending, revoked
            $table->string('status', 24)->default('pending');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module'], 'user_module_access_uq');
            $table->index(['module', 'status'], 'user_module_access_module_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_accesses');
    }
};

