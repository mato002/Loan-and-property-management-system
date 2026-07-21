<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_wallets', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sms_wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('sms_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sms_template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
            $table->text('body');
            $table->json('recipients');
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sms_schedule_id')->nullable()->constrained('sms_schedules')->nullOnDelete();
            $table->string('phone', 32);
            $table->text('message');
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->decimal('charged_amount', 12, 4)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        DB::table('sms_wallets')->insert([
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_schedules');
        Schema::dropIfExists('sms_wallet_topups');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('sms_wallets');
    }
};
