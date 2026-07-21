<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_loan_sizes')) {
            Schema::create('analytics_loan_sizes', function (Blueprint $table) {
                $table->id();
                $table->string('label');
                $table->decimal('min_principal', 15, 2)->default(0);
                $table->decimal('max_principal', 15, 2)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('analytics_period_targets')) {
            Schema::create('analytics_period_targets', function (Blueprint $table) {
                $table->id();
                $table->string('branch', 120);
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->decimal('disbursement_target', 15, 2)->default(0);
                $table->decimal('collection_target', 15, 2)->default(0);
                $table->decimal('accrual_target', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['branch', 'period_year', 'period_month'], 'uniq_analytics_branch_period');
            });
        }

        if (! Schema::hasTable('analytics_performance_records')) {
            Schema::create('analytics_performance_records', function (Blueprint $table) {
                $table->id();
                $table->date('record_date');
                $table->string('branch', 120)->nullable();
                $table->decimal('total_outstanding', 15, 2)->nullable();
                $table->decimal('disbursements_period', 15, 2)->nullable();
                $table->decimal('collections_period', 15, 2)->nullable();
                $table->decimal('npl_rate', 5, 2)->nullable();
                $table->unsignedInteger('active_borrowers_count')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['record_date', 'branch']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_performance_records');
        Schema::dropIfExists('analytics_period_targets');
        Schema::dropIfExists('analytics_loan_sizes');
    }
};
