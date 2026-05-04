<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_form_field_definitions')) {
            return;
        }

        if (Schema::hasTable('loan_product_form_field_overrides')) {
            Schema::drop('loan_product_form_field_overrides');
        }

        Schema::create('loan_product_form_field_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('loan_products')->cascadeOnDelete();
            $table->string('form_kind', 32);
            $table->string('field_key', 120);
            $table->boolean('is_included')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('prefill_from_previous')->default(false);
            $table->string('visible_to', 255)->nullable();
            $table->string('display_status', 32)->default('active');
            $table->unsignedSmallInteger('sort_order')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'form_kind', 'field_key'], 'lp_ff_ovr_prod_kind_key_uq');
            $table->index(['form_kind', 'field_key'], 'lp_ff_ovr_kind_key_idx');
        });

        if (! Schema::hasColumn('loan_form_field_definitions', 'product_id')) {
            return;
        }

        $kind = 'loan_settings_application';

        $legacy = DB::table('loan_form_field_definitions')
            ->where('form_kind', $kind)
            ->whereNotNull('product_id')
            ->get();

        foreach ($legacy as $row) {
            if (! Schema::hasTable('loan_product_form_field_overrides')) {
                break;
            }
            $included = ($row->field_status ?? 'active') === 'active';
            $displayStatus = ($row->field_status ?? '') === 'requires_approval' ? 'requires_approval' : 'active';

            DB::table('loan_product_form_field_overrides')->updateOrInsert(
                [
                    'product_id' => (int) $row->product_id,
                    'form_kind' => (string) $row->form_kind,
                    'field_key' => (string) $row->field_key,
                ],
                [
                    'is_included' => $included,
                    'is_required' => (bool) ($row->is_required ?? false),
                    'prefill_from_previous' => (bool) ($row->prefill_from_previous ?? false),
                    'visible_to' => $row->visible_to,
                    'display_status' => $displayStatus,
                    'sort_order' => (int) ($row->sort_order ?? 0),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('loan_form_field_definitions')->where('id', $row->id)->delete();
        }

        DB::table('loan_form_field_definitions')
            ->where('form_kind', $kind)
            ->whereNotNull('product_id')
            ->update(['product_id' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_product_form_field_overrides');
    }
};
