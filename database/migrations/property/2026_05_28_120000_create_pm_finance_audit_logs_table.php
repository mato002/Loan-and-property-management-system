<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_finance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('pm_lease_id')->nullable();
            $table->unsignedBigInteger('pm_tenant_id')->nullable();
            $table->unsignedBigInteger('pm_invoice_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['action', 'occurred_at']);
            $table->index('pm_invoice_id');
            $table->index('pm_lease_id');
            $table->index('pm_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_finance_audit_logs');
    }
};
