<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->boolean('public_listing_published')->default(false);
            $table->text('public_listing_description')->nullable();
        });

        Schema::create('property_unit_public_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_unit_id')->constrained('property_units')->cascadeOnDelete();
            $table->string('path', 512);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_unit_public_images');

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn(['public_listing_published', 'public_listing_description']);
        });
    }
};
