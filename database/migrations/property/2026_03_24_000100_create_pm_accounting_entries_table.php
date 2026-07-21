<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('entry_date');
            $table->string('account_name', 120);
            $table->string('category', 32);
            $table->string('entry_type', 16);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['entry_date', 'category']);
            $table->index(['account_name', 'entry_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_accounting_entries');
    }
};

