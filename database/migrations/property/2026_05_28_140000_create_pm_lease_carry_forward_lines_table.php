<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_lease_carry_forward_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pm_lease_id');
            $table->unsignedBigInteger('pm_tenant_id');
            $table->string('row_key', 191);
            $table->string('charge_type', 50)->nullable();
            $table->string('specific_charge', 100)->nullable();
            $table->string('period', 20)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('carry_forward_status', 20)->default('uninvoiced');
            $table->decimal('invoiced_amount', 12, 2)->default(0);
            $table->json('pm_invoice_ids')->nullable();
            $table->string('source', 40)->default('lease_json');
            $table->unsignedBigInteger('superseded_by_lease_id')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->json('audit_payload')->nullable();
            $table->timestamps();

            $table->unique(['pm_lease_id', 'row_key'], 'pm_cf_lines_lease_row_unique');
            $table->index(['pm_tenant_id', 'carry_forward_status'], 'pm_cf_lines_tenant_status');
            $table->index('carry_forward_status');
        });

        if (Schema::hasTable('pm_tenants') && ! Schema::hasColumn('pm_tenants', 'opening_arrears_status')) {
            Schema::table('pm_tenants', function (Blueprint $table) {
                $table->string('opening_arrears_status', 20)->default('active')->after('opening_arrears_amount');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_lease_carry_forward_lines');

        if (Schema::hasTable('pm_tenants') && Schema::hasColumn('pm_tenants', 'opening_arrears_status')) {
            Schema::table('pm_tenants', function (Blueprint $table) {
                $table->dropColumn('opening_arrears_status');
            });
        }
    }
};
