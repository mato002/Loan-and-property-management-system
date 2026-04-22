<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_requisitions')) {
            return;
        }

        Schema::table('accounting_requisitions', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_requisitions', 'form_meta')) {
                $table->json('form_meta')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_requisitions')) {
            return;
        }

        Schema::table('accounting_requisitions', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_requisitions', 'form_meta')) {
                $table->dropColumn('form_meta');
            }
        });
    }
};
