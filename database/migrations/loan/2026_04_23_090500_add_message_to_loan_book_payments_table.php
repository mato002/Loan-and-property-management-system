<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_payments')) {
            return;
        }

        if (! Schema::hasColumn('loan_book_payments', 'message')) {
            Schema::table('loan_book_payments', function (Blueprint $table) {
                $table->longText('message')->nullable()->after('notes');
            });
        }

        if (! Schema::hasTable('loan_book_payment_sms_ingests')) {
            return;
        }

        DB::table('loan_book_payment_sms_ingests as ingest')
            ->join('loan_book_payments as payment', 'payment.id', '=', 'ingest.loan_book_payment_id')
            ->whereNotNull('ingest.raw_message')
            ->where('ingest.raw_message', '!=', '')
            ->where(function ($query) {
                $query->whereNull('payment.message')
                    ->orWhere('payment.message', '=');
            })
            ->select('payment.id', 'ingest.raw_message')
            ->orderBy('payment.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('loan_book_payments')
                        ->where('id', $row->id)
                        ->update(['message' => $row->raw_message]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_payments') || ! Schema::hasColumn('loan_book_payments', 'message')) {
            return;
        }

        Schema::table('loan_book_payments', function (Blueprint $table) {
            $table->dropColumn('message');
        });
    }
};
