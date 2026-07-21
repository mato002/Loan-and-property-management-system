<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_performance_indicator_targets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('new_target', 14, 2)->default(20);
            $table->decimal('repeat_target', 14, 2)->default(10);
            $table->decimal('arrears_target', 14, 2)->default(0);
            $table->decimal('performing_target', 14, 2)->default(70);
            $table->decimal('gross_target', 14, 2)->default(500000);
            $table->decimal('revenue_target', 14, 2)->default(170000);
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month'], 'loan_perf_targets_emp_year_month_unique');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_performance_indicator_targets');
    }
};
