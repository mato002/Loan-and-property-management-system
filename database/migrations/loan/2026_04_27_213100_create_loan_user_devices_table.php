<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_user_devices')) {
            return;
        }

        Schema::create('loan_user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint_hash', 128);
            $table->string('fingerprint_label', 180)->nullable();
            $table->boolean('is_trusted')->default(true);
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 64)->nullable();
            $table->text('last_seen_user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint_hash'], 'loan_user_devices_user_fp_uq');
            $table->index(['user_id', 'is_trusted'], 'loan_user_devices_user_trusted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_user_devices');
    }
};

