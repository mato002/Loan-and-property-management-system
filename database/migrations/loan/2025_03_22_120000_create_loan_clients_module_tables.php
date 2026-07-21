<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            Schema::create('loan_clients', function (Blueprint $table) {
                $table->id();
                $table->string('client_number', 50)->unique();
                $table->string('kind', 20)->default('client');
                $table->string('first_name', 120);
                $table->string('last_name', 120);
                $table->string('phone', 40)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('id_number', 80)->nullable();
                $table->text('address')->nullable();
                $table->string('branch', 120)->nullable();
                $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('lead_status', 40)->nullable();
                $table->string('client_status', 40)->default('active');
                $table->text('notes')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
                $table->index(['kind', 'created_at']);
            });
        }

        if (! Schema::hasTable('default_client_groups')) {
            Schema::create('default_client_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('default_client_group_loan_client')) {
            Schema::create('default_client_group_loan_client', function (Blueprint $table) {
                $table->id();
                $table->foreignId('default_client_group_id')->constrained('default_client_groups')->cascadeOnDelete();
                $table->foreignId('loan_client_id')->constrained('loan_clients')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['default_client_group_id', 'loan_client_id'], 'uniq_dcg_loan_client_pair');
            });
        }

        if (! Schema::hasTable('client_interactions')) {
            Schema::create('client_interactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_client_id')->constrained('loan_clients')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('interaction_type', 40);
                $table->string('subject', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('interacted_at');
                $table->timestamps();
                $table->index(['loan_client_id', 'interacted_at']);
            });
        }

        if (! Schema::hasTable('client_transfers')) {
            Schema::create('client_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_client_id')->constrained('loan_clients')->cascadeOnDelete();
                $table->string('from_branch', 120)->nullable();
                $table->string('to_branch', 120)->nullable();
                $table->foreignId('from_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->foreignId('transferred_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_transfers');
        Schema::dropIfExists('client_interactions');
        Schema::dropIfExists('default_client_group_loan_client');
        Schema::dropIfExists('default_client_groups');
        Schema::dropIfExists('loan_clients');
    }
};
