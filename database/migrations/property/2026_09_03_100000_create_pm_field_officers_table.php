<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_field_officers')) {
            Schema::create('pm_field_officers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->string('phone', 32)->nullable();
                $table->boolean('portal_access')->default(false);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['agent_user_id', 'name']);
            });
        }

        if (Schema::hasTable('properties') && ! Schema::hasColumn('properties', 'field_officer_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->foreignId('field_officer_id')
                    ->nullable()
                    ->after('agent_user_id')
                    ->constrained('pm_field_officers')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'field_officer_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropConstrainedForeignId('field_officer_id');
            });
        }

        Schema::dropIfExists('pm_field_officers');
    }
};
