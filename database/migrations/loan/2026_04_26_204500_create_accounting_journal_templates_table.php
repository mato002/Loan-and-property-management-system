<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_journal_templates')) {
            return;
        }

        Schema::create('accounting_journal_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('scope', 20)->default('personal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('reference_prefix', 30)->nullable();
            $table->string('default_action', 30)->default('post');
            $table->json('template_lines');
            $table->timestamps();

            $table->index(['scope', 'is_active'], 'acct_journal_templates_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_templates');
    }
};
