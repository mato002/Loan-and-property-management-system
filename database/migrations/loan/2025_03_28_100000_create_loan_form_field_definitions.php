<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_form_field_definitions')) {
            Schema::create('loan_form_field_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('form_kind', 32);
                $table->string('field_key', 120);
                $table->string('label', 255);
                $table->string('data_type', 32);
                $table->text('select_options')->nullable();
                $table->boolean('prefill_from_previous')->default(false);
                $table->boolean('is_core')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['form_kind', 'field_key']);
                $table->index(['form_kind', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_form_field_definitions');
    }
};
