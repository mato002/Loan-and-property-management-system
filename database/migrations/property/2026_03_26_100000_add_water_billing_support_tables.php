<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_invoices', function (Blueprint $table) {
            $table->string('invoice_type', 24)->default('rent')->after('status');
            $table->string('billing_period', 16)->nullable()->after('invoice_type');
            $table->index(['pm_tenant_id', 'invoice_type']);
            $table->index(['property_unit_id', 'invoice_type']);
        });

        Schema::table('pm_unit_utility_charges', function (Blueprint $table) {
            $table->string('charge_type', 24)->default('other')->after('property_unit_id');
            $table->string('billing_month', 7)->nullable()->after('charge_type');
            $table->boolean('is_invoiced')->default(false)->after('notes');
            $table->unsignedBigInteger('pm_invoice_id')->nullable()->after('is_invoiced');
            $table->foreign('pm_invoice_id')->references('id')->on('pm_invoices')->nullOnDelete();
            $table->index(['charge_type', 'billing_month']);
        });

        Schema::create('pm_water_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_unit_id')->constrained('property_units')->cascadeOnDelete();
            $table->string('billing_month', 7); // YYYY-MM
            $table->decimal('previous_reading', 14, 3);
            $table->decimal('current_reading', 14, 3);
            $table->decimal('units_used', 14, 3);
            $table->decimal('rate_per_unit', 14, 2);
            $table->decimal('fixed_charge', 14, 2)->default(0);
            $table->decimal('amount', 14, 2);
            $table->string('status', 24)->default('recorded');
            $table->unsignedBigInteger('pm_invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('pm_invoice_id')->references('id')->on('pm_invoices')->nullOnDelete();
            $table->unique(['property_unit_id', 'billing_month']);
            $table->index(['billing_month', 'status']);
        });

        Schema::table('pm_penalty_rules', function (Blueprint $table) {
            $table->unsignedInteger('grace_days')->default(0)->after('trigger_event');
        });
    }

    public function down(): void
    {
        Schema::table('pm_penalty_rules', function (Blueprint $table) {
            $table->dropColumn('grace_days');
        });

        Schema::dropIfExists('pm_water_readings');

        Schema::table('pm_unit_utility_charges', function (Blueprint $table) {
            $table->dropForeign(['pm_invoice_id']);
            $table->dropColumn(['charge_type', 'billing_month', 'is_invoiced', 'pm_invoice_id']);
        });

        Schema::table('pm_invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_type', 'billing_period']);
        });
    }
};
