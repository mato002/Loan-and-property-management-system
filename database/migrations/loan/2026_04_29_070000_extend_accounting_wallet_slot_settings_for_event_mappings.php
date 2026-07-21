<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_wallet_slot_settings')) {
            return;
        }

        Schema::table('accounting_wallet_slot_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_wallet_slot_settings', 'approval_status')) {
                $table->string('approval_status', 32)->default('needs_setup')->after('accounting_chart_account_id');
            }
            if (! Schema::hasColumn('accounting_wallet_slot_settings', 'last_updated_by')) {
                $table->unsignedBigInteger('last_updated_by')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('accounting_wallet_slot_settings', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('last_updated_by');
            }
            if (! Schema::hasColumn('accounting_wallet_slot_settings', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('accounting_wallet_slot_settings', 'history_json')) {
                $table->json('history_json')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_wallet_slot_settings')) {
            return;
        }

        Schema::table('accounting_wallet_slot_settings', function (Blueprint $table): void {
            foreach (['history_json', 'approved_at', 'approved_by', 'last_updated_by', 'approval_status'] as $col) {
                if (Schema::hasColumn('accounting_wallet_slot_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
