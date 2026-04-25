<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_control_audits')) {
            return;
        }

        Schema::create('accounting_control_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action', 64);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'acct_control_audits_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_control_audits');
    }
};
