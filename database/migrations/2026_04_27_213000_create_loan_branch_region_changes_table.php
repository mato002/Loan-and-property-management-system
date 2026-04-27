<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_branch_region_changes')) {
            return;
        }

        Schema::create('loan_branch_region_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_branch_id')->constrained('loan_branches')->cascadeOnDelete();
            $table->foreignId('from_loan_region_id')->nullable()->constrained('loan_regions')->nullOnDelete();
            $table->foreignId('to_loan_region_id')->nullable()->constrained('loan_regions')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('approved');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['loan_branch_id', 'status']);
            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_branch_region_changes');
    }
};

