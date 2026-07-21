<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_payments')) {
            return;
        }

        if (! Schema::hasColumn('pm_payments', 'agent_user_id')) {
            Schema::table('pm_payments', function (Blueprint $table) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('pm_tenant_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('agent_user_id', 'pm_payments_agent_user_id_idx');
            });
        }

        if (! Schema::hasTable('pm_invoices') || ! Schema::hasColumn('pm_invoices', 'agent_user_id')) {
            return;
        }

        // Backfill from the linked invoice when we can infer it.
        DB::statement(<<<'SQL'
            UPDATE pm_payments pay
            INNER JOIN pm_payment_allocations a ON a.pm_payment_id = pay.id
            INNER JOIN pm_invoices i ON i.id = a.pm_invoice_id
            SET pay.agent_user_id = i.agent_user_id
            WHERE pay.agent_user_id IS NULL
              AND i.agent_user_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_payments') || ! Schema::hasColumn('pm_payments', 'agent_user_id')) {
            return;
        }

        Schema::table('pm_payments', function (Blueprint $table) {
            $table->dropIndex('pm_payments_agent_user_id_idx');
            $table->dropConstrainedForeignId('agent_user_id');
        });
    }
};
