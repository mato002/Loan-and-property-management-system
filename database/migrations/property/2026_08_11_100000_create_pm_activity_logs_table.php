<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_activity_logs')) {
            return;
        }

        Schema::create('pm_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('portal_role', 32)->nullable();
            $table->string('source', 64);
            $table->string('action', 120);
            $table->string('summary', 500);
            $table->string('entity_type', 80)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('pm_lease_id')->nullable();
            $table->unsignedBigInteger('pm_tenant_id')->nullable();
            $table->unsignedBigInteger('pm_invoice_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['occurred_at', 'id']);
            $table->index(['source', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index('actor_user_id');
            $table->index('pm_lease_id');
            $table->index('pm_tenant_id');
            $table->index('pm_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_activity_logs');
    }
};
