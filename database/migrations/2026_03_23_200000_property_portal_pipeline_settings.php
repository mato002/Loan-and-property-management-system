<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_portal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_penalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope')->default('global');
            $table->string('trigger_event')->default('days_after_due');
            $table->string('formula')->default('percent_of_rent');
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('percent', 8, 4)->nullable();
            $table->decimal('cap', 14, 2)->nullable();
            $table->date('effective_from')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pm_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 16);
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('pm_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('to_address');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('pm_listing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->string('stage', 32)->default('new');
            $table->text('notes')->nullable();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pm_listing_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
            $table->string('applicant_name');
            $table->string('applicant_phone')->nullable();
            $table->string('applicant_email')->nullable();
            $table->string('status', 32)->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_unit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_unit_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type', 32);
            $table->string('status', 32)->default('planned');
            $table->date('scheduled_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pm_tenant_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_tenant_id')->constrained('pm_tenants')->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
            $table->string('notice_type', 64);
            $table->string('status', 32)->default('draft');
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_tenant_notices');
        Schema::dropIfExists('pm_unit_movements');
        Schema::dropIfExists('pm_listing_applications');
        Schema::dropIfExists('pm_listing_leads');
        Schema::dropIfExists('pm_message_logs');
        Schema::dropIfExists('pm_message_templates');
        Schema::dropIfExists('pm_penalty_rules');
        Schema::dropIfExists('property_portal_settings');
    }
};
