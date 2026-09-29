<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_units')) {
            return;
        }

        Schema::table('property_units', function (Blueprint $table) {
            if (! Schema::hasColumn('property_units', 'bathrooms')) {
                $table->unsignedTinyInteger('bathrooms')->nullable();
            }
            if (! Schema::hasColumn('property_units', 'parking_spaces')) {
                $table->unsignedTinyInteger('parking_spaces')->nullable();
            }
            if (! Schema::hasColumn('property_units', 'rent_per_area')) {
                $table->decimal('rent_per_area', 14, 2)->nullable();
            }
            if (! Schema::hasColumn('property_units', 'charge_frequency')) {
                $table->string('charge_frequency', 24)->nullable();
            }
            if (! Schema::hasColumn('property_units', 'take_on_letting_date')) {
                $table->date('take_on_letting_date')->nullable();
            }
            if (! Schema::hasColumn('property_units', 'unit_sequence')) {
                $table->unsignedInteger('unit_sequence')->nullable();
            }
            if (! Schema::hasColumn('property_units', 'floor_number')) {
                $table->string('floor_number', 32)->nullable();
            }
            if (! Schema::hasColumn('property_units', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('property_units', 'location_notes')) {
                $table->text('location_notes')->nullable();
            }
            if (! Schema::hasColumn('property_units', 'electricity_account')) {
                $table->string('electricity_account', 64)->nullable();
            }
            if (! Schema::hasColumn('property_units', 'electricity_meter')) {
                $table->string('electricity_meter', 64)->nullable();
            }
            if (! Schema::hasColumn('property_units', 'water_account')) {
                $table->string('water_account', 64)->nullable();
            }
            if (! Schema::hasColumn('property_units', 'water_meter')) {
                $table->string('water_meter', 64)->nullable();
            }
            if (! Schema::hasColumn('property_units', 'extra_meters')) {
                $table->json('extra_meters')->nullable();
            }
            if (! Schema::hasColumn('property_units', 'features')) {
                $table->json('features')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('property_units')) {
            return;
        }

        Schema::table('property_units', function (Blueprint $table) {
            $columns = [
                'bathrooms', 'parking_spaces', 'rent_per_area', 'charge_frequency',
                'take_on_letting_date', 'unit_sequence', 'floor_number', 'notes',
                'location_notes', 'electricity_account', 'electricity_meter',
                'water_account', 'water_meter', 'extra_meters', 'features',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('property_units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
