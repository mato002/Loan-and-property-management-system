<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
        });

        Schema::create('property_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedTinyInteger('bedrooms')->default(1);
            $table->decimal('rent_amount', 14, 2)->default(0);
            $table->string('status', 32)->default('vacant');
            $table->date('vacant_since')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'label']);
        });

        Schema::create('property_landlord', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('ownership_percent', 6, 2)->default(100);
            $table->timestamps();
            $table->unique(['property_id', 'user_id']);
        });

        Schema::create('pm_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('national_id')->nullable();
            $table->string('risk_level', 24)->default('normal');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_tenant_id')->constrained('pm_tenants')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('monthly_rent', 14, 2)->default(0);
            $table->decimal('deposit_amount', 14, 2)->default(0);
            $table->string('status', 24)->default('draft');
            $table->text('terms_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_lease_unit', function (Blueprint $table) {
            $table->foreignId('pm_lease_id')->constrained('pm_leases')->cascadeOnDelete();
            $table->foreignId('property_unit_id')->constrained('property_units')->cascadeOnDelete();
            $table->primary(['pm_lease_id', 'property_unit_id']);
        });

        Schema::create('pm_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_lease_id')->nullable()->constrained('pm_leases')->nullOnDelete();
            $table->foreignId('property_unit_id')->constrained('property_units')->cascadeOnDelete();
            $table->foreignId('pm_tenant_id')->constrained('pm_tenants')->cascadeOnDelete();
            $table->string('invoice_no')->unique();
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status', 24)->default('draft');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_tenant_id')->constrained('pm_tenants')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->decimal('amount', 14, 2);
            $table->string('external_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_payment_id')->constrained('pm_payments')->cascadeOnDelete();
            $table->foreignId('pm_invoice_id')->constrained('pm_invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('pm_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status', 24)->default('active');
            $table->decimal('rating', 3, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('pm_maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_unit_id')->constrained('property_units')->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 64);
            $table->text('description');
            $table->string('urgency', 24)->default('normal');
            $table->string('status', 24)->default('open');
            $table->timestamps();
        });

        Schema::create('pm_maintenance_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_maintenance_request_id')->constrained('pm_maintenance_requests')->cascadeOnDelete();
            $table->foreignId('pm_vendor_id')->nullable()->constrained('pm_vendors')->nullOnDelete();
            $table->decimal('quote_amount', 14, 2)->nullable();
            $table->string('status', 24)->default('quoted');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_landlord_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('description');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_landlord_ledger_entries');
        Schema::dropIfExists('pm_maintenance_jobs');
        Schema::dropIfExists('pm_maintenance_requests');
        Schema::dropIfExists('pm_vendors');
        Schema::dropIfExists('pm_payment_allocations');
        Schema::dropIfExists('pm_payments');
        Schema::dropIfExists('pm_invoices');
        Schema::dropIfExists('pm_lease_unit');
        Schema::dropIfExists('pm_leases');
        Schema::dropIfExists('pm_tenants');
        Schema::dropIfExists('property_landlord');
        Schema::dropIfExists('property_units');
        Schema::dropIfExists('properties');
    }
};
