<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_payments')) {
            return;
        }

        if (Schema::hasColumn('loan_book_payments', 'funded_from_wallet')) {
            return;
        }

        Schema::table('loan_book_payments', function (Blueprint $table): void {
            $table->boolean('funded_from_wallet')->default(false)->after('payment_kind');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_book_payments') && Schema::hasColumn('loan_book_payments', 'funded_from_wallet')) {
            Schema::table('loan_book_payments', function (Blueprint $table): void {
                $table->dropColumn('funded_from_wallet');
            });
        }
    }
};
