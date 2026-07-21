<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounting_posting_rules')
            ->where('rule_key', 'loan_interests')
            ->update(['is_editable' => true]);
    }

    public function down(): void
    {
        DB::table('accounting_posting_rules')
            ->where('rule_key', 'loan_interests')
            ->update(['is_editable' => false]);
    }
};
