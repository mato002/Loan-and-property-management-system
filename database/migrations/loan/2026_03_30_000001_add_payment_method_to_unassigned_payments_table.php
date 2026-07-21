<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unassigned_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('unassigned_payments', 'payment_method')) {
                $table->string('payment_method', 32)->nullable()->after('phone');
                $table->index('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unassigned_payments', function (Blueprint $table) {
            if (Schema::hasColumn('unassigned_payments', 'payment_method')) {
                $table->dropIndex(['payment_method']);
                $table->dropColumn('payment_method');
            }
        });
    }
};

