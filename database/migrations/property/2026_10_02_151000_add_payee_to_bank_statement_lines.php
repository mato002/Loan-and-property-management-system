<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_bank_statement_lines')) {
            return;
        }

        Schema::table('pm_bank_statement_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('pm_bank_statement_lines', 'paid_to_kind')) {
                $table->string('paid_to_kind', 24)->nullable()->after('unassigned_payment_id');
            }
            if (! Schema::hasColumn('pm_bank_statement_lines', 'paid_to_landlord_id')) {
                $table->unsignedBigInteger('paid_to_landlord_id')->nullable()->after('paid_to_kind');
            }
            if (! Schema::hasColumn('pm_bank_statement_lines', 'paid_to_name')) {
                $table->string('paid_to_name', 191)->nullable()->after('paid_to_landlord_id');
            }
            if (! Schema::hasColumn('pm_bank_statement_lines', 'paid_to_note')) {
                $table->string('paid_to_note', 255)->nullable()->after('paid_to_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_bank_statement_lines')) {
            return;
        }

        Schema::table('pm_bank_statement_lines', function (Blueprint $table): void {
            foreach (['paid_to_note', 'paid_to_name', 'paid_to_landlord_id', 'paid_to_kind'] as $column) {
                if (Schema::hasColumn('pm_bank_statement_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
