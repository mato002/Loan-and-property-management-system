<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_property_expense_requisitions')
            && ! Schema::hasColumn('pm_property_expense_requisitions', 'attachment_path')) {
            Schema::table('pm_property_expense_requisitions', function (Blueprint $table): void {
                $table->string('attachment_path', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_property_expense_requisitions')
            && Schema::hasColumn('pm_property_expense_requisitions', 'attachment_path')) {
            Schema::table('pm_property_expense_requisitions', function (Blueprint $table): void {
                $table->dropColumn('attachment_path');
            });
        }
    }
};
