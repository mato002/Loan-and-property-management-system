<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_form_field_definitions')) {
            return;
        }

        Schema::table('loan_form_field_definitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_form_field_definitions', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('form_kind')->constrained('loan_products')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_form_field_definitions', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('data_type');
            }
            if (! Schema::hasColumn('loan_form_field_definitions', 'visible_to')) {
                $table->string('visible_to', 255)->nullable()->after('prefill_from_previous');
            }
            if (! Schema::hasColumn('loan_form_field_definitions', 'field_status')) {
                $table->string('field_status', 32)->default('active')->after('is_core');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_form_field_definitions')) {
            return;
        }

        Schema::table('loan_form_field_definitions', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_form_field_definitions', 'field_status')) {
                $table->dropColumn('field_status');
            }
            if (Schema::hasColumn('loan_form_field_definitions', 'visible_to')) {
                $table->dropColumn('visible_to');
            }
            if (Schema::hasColumn('loan_form_field_definitions', 'is_required')) {
                $table->dropColumn('is_required');
            }
            if (Schema::hasColumn('loan_form_field_definitions', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }
        });
    }
};
