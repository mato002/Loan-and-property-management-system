<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_payments') || ! Schema::hasColumn('pm_payments', 'external_ref')) {
            return;
        }

        $duplicates = DB::table('pm_payments')
            ->select('external_ref')
            ->whereNotNull('external_ref')
            ->where('external_ref', '!=', '')
            ->groupBy('external_ref')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('external_ref');

        foreach ($duplicates as $ref) {
            $ids = DB::table('pm_payments')
                ->where('external_ref', $ref)
                ->orderByDesc('id')
                ->pluck('id');

            $ids->slice(1)->each(function ($id) use ($ref): void {
                DB::table('pm_payments')
                    ->where('id', $id)
                    ->update(['external_ref' => $ref.'-DUP-'.$id]);
            });
        }

        DB::table('pm_payments')->where('external_ref', '')->update(['external_ref' => null]);

        try {
            Schema::table('pm_payments', function (Blueprint $table): void {
                $table->unique('external_ref', 'pm_payments_external_ref_unique');
            });
        } catch (\Throwable) {
            // Index may already exist.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_payments')) {
            return;
        }

        try {
            Schema::table('pm_payments', function (Blueprint $table): void {
                $table->dropUnique('pm_payments_external_ref_unique');
            });
        } catch (\Throwable) {
            // ignore
        }
    }
};
