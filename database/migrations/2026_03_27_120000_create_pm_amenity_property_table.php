<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_amenity_property', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_amenity_id')->constrained('pm_amenities')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pm_amenity_id', 'property_id']);
        });

        // Backfill property-level tags from existing unit-level amenity links.
        DB::statement('
            INSERT INTO pm_amenity_property (pm_amenity_id, property_id, created_at, updated_at)
            SELECT DISTINCT pau.pm_amenity_id, pu.property_id, NOW(), NOW()
            FROM pm_amenity_unit pau
            INNER JOIN property_units pu ON pu.id = pau.property_unit_id
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_amenity_property');
    }
};
