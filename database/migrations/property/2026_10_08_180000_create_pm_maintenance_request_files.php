<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_maintenance_requests') || Schema::hasTable('pm_maintenance_request_files')) {
            return;
        }

        Schema::create('pm_maintenance_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_maintenance_request_id')->constrained('pm_maintenance_requests')->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_maintenance_request_files');
    }
};
