<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_landlord_portal_profiles')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_landlord_portal_profiles', 'landlord_type')) {
                    $table->string('landlord_type', 32)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_portal_profiles', 'location')) {
                    $table->string('location', 128)->nullable();
                }
            });
        }

        if (! Schema::hasTable('pm_landlord_documents')) {
            Schema::create('pm_landlord_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('path', 512);
                $table->string('original_filename', 255)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('mime', 128)->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_landlord_documents');

        if (Schema::hasTable('pm_landlord_portal_profiles')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
                foreach (['landlord_type', 'location'] as $column) {
                    if (Schema::hasColumn('pm_landlord_portal_profiles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
