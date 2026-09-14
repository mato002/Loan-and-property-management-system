<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_bank_statements')) {
            Schema::create('pm_bank_statements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('source_key', 64);
                $table->string('bank_name', 80)->default('Co-operative Bank');
                $table->string('account_no', 32);
                $table->string('account_name', 191)->nullable();
                $table->string('currency', 8)->default('KES');
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->decimal('closing_balance', 14, 2)->default(0);
                $table->decimal('total_debit', 14, 2)->default(0);
                $table->decimal('total_credit', 14, 2)->default(0);
                $table->string('source_filename', 191)->nullable();
                $table->timestamps();

                $table->unique(['agent_user_id', 'source_key'], 'pm_bank_stmt_agent_source_unique');
                $table->index(['agent_user_id', 'period_from']);
                $table->index(['agent_user_id', 'account_no']);
            });
        }

        if (! Schema::hasTable('pm_bank_statement_lines')) {
            Schema::create('pm_bank_statement_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pm_bank_statement_id')->constrained('pm_bank_statements')->cascadeOnDelete();
                $table->string('source_key', 64);
                $table->string('line_type', 32);
                $table->string('direction', 8);
                $table->date('txn_date')->nullable();
                $table->string('reference', 64);
                $table->decimal('amount', 14, 2);
                $table->decimal('running_balance', 14, 2)->nullable();
                $table->string('counterparty', 191)->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('narration', 255)->nullable();
                $table->string('match_status', 32)->default('unmatched');
                $table->string('matched_type', 32)->nullable();
                $table->unsignedBigInteger('pm_payment_id')->nullable();
                $table->unsignedBigInteger('pm_ezen_receipt_register_id')->nullable();
                $table->unsignedBigInteger('unassigned_payment_id')->nullable();
                $table->timestamps();

                $table->unique(['agent_user_id', 'source_key'], 'pm_bank_line_agent_source_unique');
                $table->index(['agent_user_id', 'txn_date']);
                $table->index(['agent_user_id', 'reference']);
                $table->index(['agent_user_id', 'match_status']);
                $table->index(['pm_bank_statement_id', 'direction']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_bank_statement_lines');
        Schema::dropIfExists('pm_bank_statements');
    }
};
