<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'pm_invoices_issue_date_idx';

    public function up(): void
    {
        if (! Schema::hasTable('pm_invoices') || ! Schema::hasColumn('pm_invoices', 'issue_date')) {
            return;
        }

        Schema::table('pm_invoices', function (Blueprint $table): void {
            $table->index('issue_date', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_invoices')) {
            return;
        }

        Schema::table('pm_invoices', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
