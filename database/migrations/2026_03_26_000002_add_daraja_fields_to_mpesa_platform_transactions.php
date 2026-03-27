<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpesa_platform_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('mpesa_platform_transactions', 'conversation_id')) {
                $table->string('conversation_id', 120)->nullable()->index();
            }
            if (! Schema::hasColumn('mpesa_platform_transactions', 'originator_conversation_id')) {
                $table->string('originator_conversation_id', 120)->nullable()->index();
            }
            if (! Schema::hasColumn('mpesa_platform_transactions', 'transaction_id')) {
                $table->string('transaction_id', 120)->nullable()->index();
            }
            if (! Schema::hasColumn('mpesa_platform_transactions', 'result_code')) {
                $table->integer('result_code')->nullable()->index();
            }
            if (! Schema::hasColumn('mpesa_platform_transactions', 'result_desc')) {
                $table->string('result_desc', 2000)->nullable();
            }
            if (! Schema::hasColumn('mpesa_platform_transactions', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_platform_transactions', function (Blueprint $table) {
            foreach ([
                'conversation_id',
                'originator_conversation_id',
                'transaction_id',
                'result_code',
                'result_desc',
                'meta',
            ] as $col) {
                if (Schema::hasColumn('mpesa_platform_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

