<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_products')) {
            return;
        }

        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'status')) {
                $table->string('status', 16)->default('active')->after('is_active');
            }
            if (! Schema::hasColumn('loan_products', 'deleted_at')) {
                $table->softDeletes();
            }
            if (! Schema::hasColumn('loan_products', 'product_code')) {
                $table->string('product_code', 64)->nullable()->unique()->after('name');
            }
        });

        if (Schema::hasColumn('loan_products', 'status')) {
            if (Schema::hasColumn('loan_products', 'is_active')) {
                DB::table('loan_products')->update([
                    'status' => DB::raw("CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END"),
                ]);
            } else {
                DB::table('loan_products')
                    ->whereNull('status')
                    ->update(['status' => 'active']);
            }
        }

        if (Schema::hasColumn('loan_products', 'is_active') && Schema::hasColumn('loan_products', 'status')) {
            DB::table('loan_products')->update([
                'is_active' => DB::raw("CASE WHEN status = 'active' THEN 1 ELSE 0 END"),
            ]);
        }

        if (Schema::hasColumn('loan_products', 'product_code')) {
            DB::table('loan_products')
                ->select(['id'])
                ->whereNull('product_code')
                ->orderBy('id')
                ->get()
                ->each(function (object $row): void {
                    DB::table('loan_products')
                        ->where('id', (int) $row->id)
                        ->update(['product_code' => 'LP-'.str_pad((string) $row->id, 8, '0', STR_PAD_LEFT)]);
                });
        }
    }

    public function down(): void
    {
        // Intentionally irreversible to avoid dropping pre-existing columns.
    }
};

