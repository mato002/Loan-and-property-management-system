<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('min_units');
            $table->integer('max_units');
            $table->decimal('monthly_price_ksh', 10, 2);
            $table->decimal('annual_price_ksh', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->unique(['min_units', 'max_units']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
