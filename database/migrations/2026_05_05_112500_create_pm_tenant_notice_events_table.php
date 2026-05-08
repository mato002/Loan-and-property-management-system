<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_tenant_notice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained('pm_tenant_notices')->cascadeOnDelete();
            $table->string('event_type', 32)->default('status_changed');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['notice_id', 'created_at']);
            $table->index(['event_type', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_tenant_notice_events');
    }
};
