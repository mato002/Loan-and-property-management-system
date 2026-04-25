<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_chart_accounts', 'account_class')) {
                $table->enum('account_class', ['Header', 'Parent', 'Detail'])->default('Detail')->after('account_type');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'is_controlled_account')) {
                $table->boolean('is_controlled_account')->default(false)->after('approval_history');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_requires_approval')) {
                $table->boolean('control_requires_approval')->default(true)->after('is_controlled_account');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_approval_type')) {
                $table->string('control_approval_type', 20)->default('any')->after('control_requires_approval');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_approval_role')) {
                $table->string('control_approval_role', 80)->nullable()->after('control_approval_type');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_always_require_approval')) {
                $table->boolean('control_always_require_approval')->default(false)->after('control_approval_role');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_threshold_enabled')) {
                $table->boolean('control_threshold_enabled')->default(false)->after('control_always_require_approval');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_threshold_amount')) {
                $table->decimal('control_threshold_amount', 15, 2)->nullable()->after('control_threshold_enabled');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_applies_to')) {
                $table->string('control_applies_to', 10)->default('both')->after('control_threshold_amount');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'control_reason_note')) {
                $table->string('control_reason_note', 500)->nullable()->after('control_applies_to');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'floor_enabled')) {
                $table->boolean('floor_enabled')->default(false)->after('control_reason_note');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'floor_action')) {
                $table->string('floor_action', 24)->default('block')->after('floor_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table): void {
            foreach ([
                'floor_action',
                'floor_enabled',
                'control_reason_note',
                'control_applies_to',
                'control_threshold_amount',
                'control_threshold_enabled',
                'control_always_require_approval',
                'control_approval_role',
                'control_approval_type',
                'control_requires_approval',
                'is_controlled_account',
            ] as $column) {
                if (Schema::hasColumn('accounting_chart_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
