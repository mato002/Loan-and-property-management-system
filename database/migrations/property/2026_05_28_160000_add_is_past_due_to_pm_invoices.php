<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_invoices', 'is_past_due')) {
                $table->boolean('is_past_due')->default(false)->after('status');
            }
        });

        if (! Schema::hasColumn('pm_invoices', 'is_past_due')) {
            return;
        }

        DB::table('pm_invoices')
            ->where('status', 'overdue')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $amount = (float) ($row->amount ?? 0);
                    $paid = (float) ($row->amount_paid ?? 0);
                    $balance = max(0.0, $amount - $paid);
                    $status = $paid >= $amount - 0.009
                        ? 'paid'
                        : ($paid > 0.009 ? 'partial' : 'sent');
                    $isPastDue = $balance > 0.009
                        && ! in_array($status, ['draft', 'cancelled', 'paid'], true)
                        && ! empty($row->due_date)
                        && $row->due_date < now()->toDateString();

                    DB::table('pm_invoices')->where('id', $row->id)->update([
                        'status' => $status,
                        'is_past_due' => $isPastDue,
                    ]);
                }
            });

        DB::table('pm_invoices')
            ->whereNotIn('status', ['draft', 'cancelled', 'paid', 'overdue'])
            ->whereRaw('GREATEST(0, amount - COALESCE(amount_paid, 0)) > 0')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['is_past_due' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('pm_invoices', 'is_past_due')) {
            Schema::table('pm_invoices', function (Blueprint $table) {
                $table->dropColumn('is_past_due');
            });
        }
    }
};
