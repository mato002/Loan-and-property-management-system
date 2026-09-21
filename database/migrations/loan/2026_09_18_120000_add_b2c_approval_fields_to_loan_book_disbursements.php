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

        Schema::table('loan_book_disbursements', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_requested_by')) {
                $table->foreignId('payout_requested_by')->nullable()->after('payout_meta')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_approved_by')) {
                $table->foreignId('payout_approved_by')->nullable()->after('payout_requested_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_approved_at')) {
                $table->timestamp('payout_approved_at')->nullable()->after('payout_approved_by');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_rejected_by')) {
                $table->foreignId('payout_rejected_by')->nullable()->after('payout_approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_rejected_at')) {
                $table->timestamp('payout_rejected_at')->nullable()->after('payout_rejected_by');
            }
            if (! Schema::hasColumn('loan_book_disbursements', 'payout_reject_reason')) {
                $table->string('payout_reject_reason', 500)->nullable()->after('payout_rejected_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_disbursements')) {
            return;
        }

        Schema::table('loan_book_disbursements', function (Blueprint $table): void {
            foreach ([
                'payout_requested_by',
                'payout_approved_by',
                'payout_approved_at',
                'payout_rejected_by',
                'payout_rejected_at',
                'payout_reject_reason',
            ] as $col) {
                if (Schema::hasColumn('loan_book_disbursements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
