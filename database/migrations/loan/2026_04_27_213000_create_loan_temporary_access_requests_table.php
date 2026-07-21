<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_temporary_access_requests')) {
            return;
        }

        Schema::create('loan_temporary_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('permission_key', 160);
            $table->string('scope', 500)->nullable();
            $table->decimal('amount_limit', 15, 2)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('decision_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['requester_user_id', 'status'], 'loan_temp_access_req_user_status_idx');
            $table->index(['permission_key', 'status'], 'loan_temp_access_req_perm_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_temporary_access_requests');
    }
};

