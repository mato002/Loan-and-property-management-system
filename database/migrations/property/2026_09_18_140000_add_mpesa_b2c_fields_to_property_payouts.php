<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_landlord_payouts')) {
            Schema::table('pm_landlord_payouts', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_provider')) {
                    $table->string('payout_provider', 32)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_phone')) {
                    $table->string('payout_phone', 32)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_status')) {
                    $table->string('payout_status', 24)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_conversation_id')) {
                    $table->string('payout_conversation_id', 80)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_originator_conversation_id')) {
                    $table->string('payout_originator_conversation_id', 80)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_transaction_id')) {
                    $table->string('payout_transaction_id', 80)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_result_desc')) {
                    $table->string('payout_result_desc', 255)->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_requested_at')) {
                    $table->timestamp('payout_requested_at')->nullable();
                }
                if (! Schema::hasColumn('pm_landlord_payouts', 'payout_meta')) {
                    $table->json('payout_meta')->nullable();
                }
            });
        }

        if (Schema::hasTable('accounting_payroll_lines')) {
            Schema::table('accounting_payroll_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_payroll_lines', 'basic_pay')) {
                    $table->decimal('basic_pay', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'allowances')) {
                    $table->decimal('allowances', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payment_status')) {
                    $table->string('payment_status', 20)->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payment_date')) {
                    $table->date('payment_date')->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payment_reference')) {
                    $table->string('payment_reference', 120)->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'email_sent_at')) {
                    $table->timestamp('email_sent_at')->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payout_provider')) {
                    $table->string('payout_provider', 32)->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payout_phone')) {
                    $table->string('payout_phone', 32)->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payout_status')) {
                    $table->string('payout_status', 24)->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payout_conversation_id')) {
                    $table->string('payout_conversation_id', 80)->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payout_originator_conversation_id')) {
                    $table->string('payout_originator_conversation_id', 80)->nullable();
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'payout_meta')) {
                    $table->json('payout_meta')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_landlord_payouts')) {
            Schema::table('pm_landlord_payouts', function (Blueprint $table): void {
                foreach ([
                    'payout_provider', 'payout_phone', 'payout_status', 'payout_conversation_id',
                    'payout_originator_conversation_id', 'payout_transaction_id', 'payout_result_desc',
                    'payout_requested_at', 'payout_meta',
                ] as $col) {
                    if (Schema::hasColumn('pm_landlord_payouts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('accounting_payroll_lines')) {
            Schema::table('accounting_payroll_lines', function (Blueprint $table): void {
                foreach ([
                    'payout_provider', 'payout_phone', 'payout_status', 'payout_conversation_id',
                    'payout_originator_conversation_id', 'payout_meta',
                ] as $col) {
                    if (Schema::hasColumn('accounting_payroll_lines', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
