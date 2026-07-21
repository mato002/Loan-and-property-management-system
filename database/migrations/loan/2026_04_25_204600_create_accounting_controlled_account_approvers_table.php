<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_controlled_account_approvers')) {
            return;
        }

        Schema::create('accounting_controlled_account_approvers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('accounting_chart_account_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['accounting_chart_account_id', 'user_id'], 'acct_control_approver_unique');
            $table->foreign('accounting_chart_account_id', 'acct_ctrl_appr_account_fk')
                ->references('id')
                ->on('accounting_chart_accounts')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'acct_ctrl_appr_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_controlled_account_approvers');
    }
};
