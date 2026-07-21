<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_wallet_refund_requests')) {
            return;
        }

        $database = (string) Schema::getConnection()->getDatabaseName();
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'client_wallet_refund_requests')
            ->where('CONSTRAINT_NAME', 'cwr_aje_fk')
            ->exists();

        if ($exists) {
            return;
        }

        Schema::table('client_wallet_refund_requests', function (Blueprint $table): void {
            $table->foreign('accounting_journal_entry_id', 'cwr_aje_fk')
                ->references('id')->on('accounting_journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_wallet_refund_requests')) {
            return;
        }

        Schema::table('client_wallet_refund_requests', function (Blueprint $table): void {
            $table->dropForeign('cwr_aje_fk');
        });
    }
};
