<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_departments')) {
            return;
        }

        Schema::create('loan_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->string('code', 40)->nullable()->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_departments');
    }
};
