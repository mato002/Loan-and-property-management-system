<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring pm_invoices up to the level needed by the full invoice lifecycle:
 *   - audit columns (created_by, sent_at/by, cancelled_at/by/reason)
 *   - agent attribution (agent_user_id) so we can reliably scope GL postings
 *   - financial totals (subtotal/discount/tax/total) so we can support line
 *     items + tax without breaking the legacy `amount` flow
 *   - notes, share_token (public download), invoice_kind, original_invoice_id
 *     (credit notes)
 *   - soft delete
 *
 * Also creates the supporting tables that the invoice UI needs:
 *   - pm_invoice_items                  : line items per invoice
 *   - pm_invoice_events                 : audit/activity log per invoice
 *   - pm_invoice_penalty_applications   : idempotency for water penalty cron
 *
 * Safe to re-run: every add is guarded by hasColumn / hasTable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pm_invoices')) {
            return;
        }

        Schema::table('pm_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_invoices', 'agent_user_id')) {
                $table->unsignedBigInteger('agent_user_id')->nullable()->after('pm_tenant_id');
                $table->index('agent_user_id');
            }
            if (! Schema::hasColumn('pm_invoices', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('agent_user_id');
            }
            if (! Schema::hasColumn('pm_invoices', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('pm_invoices', 'sent_by_user_id')) {
                $table->unsignedBigInteger('sent_by_user_id')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('pm_invoices', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('sent_by_user_id');
            }
            if (! Schema::hasColumn('pm_invoices', 'cancelled_by_user_id')) {
                $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('pm_invoices', 'cancelled_reason')) {
                $table->string('cancelled_reason', 255)->nullable()->after('cancelled_by_user_id');
            }
            if (! Schema::hasColumn('pm_invoices', 'subtotal_amount')) {
                $table->decimal('subtotal_amount', 14, 2)->nullable()->after('amount_paid');
            }
            if (! Schema::hasColumn('pm_invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('subtotal_amount');
            }
            if (! Schema::hasColumn('pm_invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('pm_invoices', 'total_amount')) {
                $table->decimal('total_amount', 14, 2)->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('pm_invoices', 'notes')) {
                $table->text('notes')->nullable()->after('description');
            }
            if (! Schema::hasColumn('pm_invoices', 'invoice_kind')) {
                // 'invoice' (default) or 'credit_note'
                $table->string('invoice_kind', 24)->default('invoice')->after('invoice_type');
            }
            if (! Schema::hasColumn('pm_invoices', 'original_invoice_id')) {
                $table->unsignedBigInteger('original_invoice_id')->nullable()->after('invoice_kind');
                $table->index('original_invoice_id');
            }
            if (! Schema::hasColumn('pm_invoices', 'share_token')) {
                $table->string('share_token', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('pm_invoices', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Backfill totals + agent attribution for legacy rows so reports
        // built on the new columns still work for old data.
        DB::statement('UPDATE pm_invoices SET total_amount = amount WHERE total_amount IS NULL');
        DB::statement('UPDATE pm_invoices SET subtotal_amount = amount WHERE subtotal_amount IS NULL');

        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'agent_user_id')) {
            DB::statement(<<<'SQL'
                UPDATE pm_invoices i
                JOIN property_units pu ON pu.id = i.property_unit_id
                JOIN properties p ON p.id = pu.property_id
                SET i.agent_user_id = p.agent_user_id
                WHERE i.agent_user_id IS NULL
                  AND p.agent_user_id IS NOT NULL
            SQL);
        }

        if (! Schema::hasTable('pm_invoice_items')) {
            Schema::create('pm_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pm_invoice_id')->constrained('pm_invoices')->cascadeOnDelete();
                $table->unsignedSmallInteger('line_no')->default(1);
                $table->string('description', 255);
                $table->decimal('quantity', 14, 3)->default(1);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('line_subtotal', 14, 2)->default(0);
                $table->decimal('discount_pct', 6, 3)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_pct', 6, 3)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                // Where this line came from (rent, water, utility, penalty, custom, ...)
                $table->string('source_type', 32)->default('custom');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->timestamps();
                $table->index(['pm_invoice_id', 'line_no']);
                $table->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('pm_invoice_events')) {
            Schema::create('pm_invoice_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pm_invoice_id')->constrained('pm_invoices')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                // issued | edited | sent | reminded | emailed | sms_sent
                // partially_paid | paid | overdue | cancelled | reopened
                // deleted | credit_note_issued | penalty_applied
                $table->string('event', 48);
                $table->string('summary', 255)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->timestamps();
                $table->index(['pm_invoice_id', 'event']);
                $table->index('occurred_at');
            });
        }

        if (! Schema::hasTable('pm_invoice_penalty_applications')) {
            Schema::create('pm_invoice_penalty_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pm_invoice_id')->constrained('pm_invoices')->cascadeOnDelete();
                $table->unsignedBigInteger('pm_penalty_rule_id');
                $table->date('threshold_date');
                $table->decimal('amount', 14, 2)->default(0);
                $table->timestamp('applied_at')->useCurrent();
                $table->timestamps();
                // Idempotency: a given rule cannot apply to the same invoice
                // for the same threshold date twice. The cron picks a single
                // threshold_date per run (today - grace_days) so re-runs are
                // safe.
                $table->unique(
                    ['pm_invoice_id', 'pm_penalty_rule_id', 'threshold_date'],
                    'pm_invoice_penalty_apps_unique'
                );
                $table->index('pm_penalty_rule_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_invoice_penalty_applications');
        Schema::dropIfExists('pm_invoice_events');
        Schema::dropIfExists('pm_invoice_items');

        if (! Schema::hasTable('pm_invoices')) {
            return;
        }

        Schema::table('pm_invoices', function (Blueprint $table) {
            foreach ([
                'agent_user_id', 'created_by_user_id',
                'sent_at', 'sent_by_user_id',
                'cancelled_at', 'cancelled_by_user_id', 'cancelled_reason',
                'subtotal_amount', 'discount_amount', 'tax_amount', 'total_amount',
                'notes', 'invoice_kind', 'original_invoice_id', 'share_token',
            ] as $column) {
                if (Schema::hasColumn('pm_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('pm_invoices', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
