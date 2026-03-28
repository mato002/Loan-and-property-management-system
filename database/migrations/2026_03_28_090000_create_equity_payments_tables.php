<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_tenants', 'account_number')) {
                $table->string('account_number')->nullable()->after('national_id');
                $table->index('account_number');
            }
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('pm_tenants')->nullOnDelete();
            $table->foreignId('pm_payment_id')->nullable()->constrained('pm_payments')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('transaction_id')->unique();
            $table->string('account_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('reference')->nullable();
            $table->string('payment_method', 32)->default('equity');
            $table->string('status', 24)->default('pending');
            $table->timestamp('transaction_date');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['status', 'transaction_date']);
            $table->index('tenant_id');
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('equity_api');
            $table->json('response');
            $table->string('status', 16);
            $table->timestamp('created_at');
            $table->index(['source', 'status']);
        });

        Schema::create('unassigned_payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('account_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('reason');
            $table->timestamp('created_at');
            $table->index('created_at');
        });

        Schema::create('equity_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 24)->default('running');
            $table->string('trigger', 24)->default('scheduler');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('message')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_sync_runs');
        Schema::dropIfExists('unassigned_payments');
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('payments');

        Schema::table('pm_tenants', function (Blueprint $table) {
            if (Schema::hasColumn('pm_tenants', 'account_number')) {
                $table->dropIndex(['account_number']);
                $table->dropColumn('account_number');
            }
        });
    }
};

