<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_amenity_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_amenity_id')->constrained('pm_amenities')->cascadeOnDelete();
            $table->foreignId('property_unit_id')->constrained('property_units')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pm_amenity_id', 'property_unit_id']);
        });

        Schema::create('pm_unit_utility_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_unit_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('pm_amenities')->insert([
            ['name' => 'Parking slot', 'category' => 'Parking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Backup generator', 'category' => 'Utilities', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Borehole / water', 'category' => 'Utilities', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Security / guard', 'category' => 'Safety', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_unit_utility_charges');
        Schema::dropIfExists('pm_amenity_unit');
        Schema::dropIfExists('pm_amenities');
    }
};
