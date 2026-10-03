<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_tenants')) {
            Schema::table('pm_tenants', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_tenants', 'bank_name')) {
                    $table->string('bank_name', 120)->nullable();
                }
                if (! Schema::hasColumn('pm_tenants', 'bank_branch')) {
                    $table->string('bank_branch', 80)->nullable();
                }
                if (! Schema::hasColumn('pm_tenants', 'bank_account_name')) {
                    $table->string('bank_account_name', 160)->nullable();
                }
                if (! Schema::hasColumn('pm_tenants', 'bank_account_number')) {
                    $table->string('bank_account_number', 64)->nullable();
                }
            });
        }

        if (Schema::hasTable('pm_tenant_deposit_refunds')) {
            return;
        }

        Schema::create('pm_tenant_deposit_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('pm_tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
            if (Schema::hasTable('pm_tenant_deposits')) {
                $table->foreignId('pm_tenant_deposit_id')->nullable()->constrained('pm_tenant_deposits')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('pm_tenant_deposit_id')->nullable();
            }
            $table->decimal('amount', 14, 2);
            $table->date('refunded_at');
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_branch', 80)->nullable();
            $table->string('bank_account_name', 160)->nullable();
            $table->string('bank_account_number', 64)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_tenant_deposit_refunds');

        if (! Schema::hasTable('pm_tenants')) {
            return;
        }

        Schema::table('pm_tenants', function (Blueprint $table) {
            foreach (['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number'] as $column) {
                if (Schema::hasColumn('pm_tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
