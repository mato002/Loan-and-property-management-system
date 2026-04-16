<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_disbursements')) {
            return;
        }

        Schema::table('loan_book_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_status')) {
                $table->string('payout_status', 24)->default('completed')->after('accounting_journal_entry_id');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_provider')) {
                $table->string('payout_provider', 32)->nullable()->after('payout_status');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_phone')) {
                $table->string('payout_phone', 32)->nullable()->after('payout_provider');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_conversation_id')) {
                $table->string('payout_conversation_id', 80)->nullable()->after('payout_phone');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_originator_conversation_id')) {
                $table->string('payout_originator_conversation_id', 80)->nullable()->after('payout_conversation_id');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_transaction_id')) {
                $table->string('payout_transaction_id', 80)->nullable()->after('payout_originator_conversation_id');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_result_code')) {
                $table->integer('payout_result_code')->nullable()->after('payout_transaction_id');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_result_desc')) {
                $table->text('payout_result_desc')->nullable()->after('payout_result_code');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_requested_at')) {
                $table->timestamp('payout_requested_at')->nullable()->after('payout_result_desc');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_completed_at')) {
                $table->timestamp('payout_completed_at')->nullable()->after('payout_requested_at');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_meta')) {
                $table->json('payout_meta')->nullable()->after('payout_completed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_disbursements')) {
            return;
        }

        Schema::table('loan_book_disbursements', function (Blueprint $table) {
            foreach ([
                'payout_status',
                'payout_provider',
                'payout_phone',
                'payout_conversation_id',
                'payout_originator_conversation_id',
                'payout_transaction_id',
                'payout_result_code',
                'payout_result_desc',
                'payout_requested_at',
                'payout_completed_at',
                'payout_meta',
            ] as $column) {
                if (Schema::hasColumn('loan_book_disbursements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
