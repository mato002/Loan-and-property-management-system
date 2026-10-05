<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_ezen_payment_vouchers')) {
            return;
        }

        Schema::table('pm_ezen_payment_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'property_unit_id')) {
                $table->foreignId('property_unit_id')
                    ->nullable()
                    ->after('property_id')
                    ->constrained('property_units')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('properties')) {
            return;
        }

        $properties = DB::table('properties')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->get(['id', 'code']);

        foreach ($properties as $property) {
            $code = strtoupper(trim((string) $property->code));
            if ($code === '') {
                continue;
            }

            DB::table('pm_ezen_payment_vouchers')
                ->whereNull('property_id')
                ->whereRaw('UPPER(property_code) = ?', [$code])
                ->update(['property_id' => $property->id]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_ezen_payment_vouchers') || ! Schema::hasColumn('pm_ezen_payment_vouchers', 'property_unit_id')) {
            return;
        }

        Schema::table('pm_ezen_payment_vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('property_unit_id');
        });
    }
};
