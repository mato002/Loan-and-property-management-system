<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_asset_measurement_units')) {
            Schema::create('loan_asset_measurement_units', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('abbreviation', 20)->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_asset_categories')) {
            Schema::create('loan_asset_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_asset_stock_items')) {
            Schema::create('loan_asset_stock_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_asset_category_id')->constrained('loan_asset_categories')->restrictOnDelete();
                $table->foreignId('loan_asset_measurement_unit_id')->constrained('loan_asset_measurement_units')->restrictOnDelete();
                $table->string('asset_code', 60)->unique();
                $table->string('name', 200);
                $table->decimal('quantity', 14, 4)->default(0);
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->string('location', 160)->nullable();
                $table->string('serial_number', 120)->nullable();
                $table->string('status', 40)->default('in_stock');
                $table->date('acquisition_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['loan_asset_category_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_asset_stock_items');
        Schema::dropIfExists('loan_asset_categories');
        Schema::dropIfExists('loan_asset_measurement_units');
    }
};
