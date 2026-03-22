<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('leave_type', 40);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_group_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_group_id')->constrained('staff_groups')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['staff_group_id', 'employee_id']);
        });

        Schema::create('staff_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('portfolio_code')->unique();
            $table->unsignedInteger('active_loans')->default(0);
            $table->decimal('outstanding_amount', 15, 2)->nullable();
            $table->decimal('par_rate', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('staff_loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('reference')->nullable()->unique();
            $table->string('product');
            $table->decimal('amount', 14, 2);
            $table->string('stage')->default('Submitted');
            $table->string('status', 30)->default('pending');
            $table->timestamps();
        });

        Schema::create('staff_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('account_ref')->nullable()->unique();
            $table->decimal('principal', 14, 2);
            $table->decimal('balance', 14, 2);
            $table->date('next_due_date')->nullable();
            $table->string('status', 20)->default('current');
            $table->timestamps();
        });

        Schema::create('workplan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workplan_items');
        Schema::dropIfExists('staff_loans');
        Schema::dropIfExists('staff_loan_applications');
        Schema::dropIfExists('staff_portfolios');
        Schema::dropIfExists('staff_group_employee');
        Schema::dropIfExists('staff_groups');
        Schema::dropIfExists('staff_leaves');
    }
};
