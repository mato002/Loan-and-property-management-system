<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepare collection payments for Co-op Bank Till matching on existing
 * pm_tenants.account_number (Ac/No), without creating a second tenant ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'tenant_account_number')) {
                    $table->string('tenant_account_number', 64)->nullable()->after('account_number');
                    $table->index('tenant_account_number', 'payments_tenant_acno_idx');
                }
                if (! Schema::hasColumn('payments', 'invoice_id')) {
                    $table->unsignedBigInteger('invoice_id')->nullable()->after('pm_payment_id');
                    $table->index('invoice_id', 'payments_invoice_id_idx');
                }
                if (! Schema::hasColumn('payments', 'currency')) {
                    $table->string('currency', 8)->default('KES')->after('amount');
                }
                if (! Schema::hasColumn('payments', 'payment_provider')) {
                    $table->string('payment_provider', 32)->nullable()->after('payment_method');
                    $table->index('payment_provider', 'payments_provider_idx');
                }
                if (! Schema::hasColumn('payments', 'payment_channel')) {
                    $table->string('payment_channel', 64)->nullable()->after('payment_provider');
                }
                if (! Schema::hasColumn('payments', 'external_transaction_reference')) {
                    $table->string('external_transaction_reference', 128)->nullable()->after('transaction_id');
                    $table->index('external_transaction_reference', 'payments_ext_txn_ref_idx');
                }
                if (! Schema::hasColumn('payments', 'provider_reference')) {
                    $table->string('provider_reference', 128)->nullable()->after('external_transaction_reference');
                }
                if (! Schema::hasColumn('payments', 'payer_name')) {
                    $table->string('payer_name', 191)->nullable()->after('phone');
                }
                if (! Schema::hasColumn('payments', 'payer_phone')) {
                    $table->string('payer_phone', 32)->nullable()->after('payer_name');
                }
                if (! Schema::hasColumn('payments', 'received_at')) {
                    $table->timestamp('received_at')->nullable()->after('transaction_date');
                }
                if (! Schema::hasColumn('payments', 'reconciliation_status')) {
                    $table->string('reconciliation_status', 32)->nullable()->after('status');
                    $table->index('reconciliation_status', 'payments_recon_status_idx');
                }
                if (! Schema::hasColumn('payments', 'reconciliation_notes')) {
                    $table->text('reconciliation_notes')->nullable()->after('reconciliation_status');
                }
            });

            // Backfill from legacy columns without changing tenant account numbers.
            if (Schema::hasColumn('payments', 'tenant_account_number')) {
                DB::table('payments')
                    ->whereNull('tenant_account_number')
                    ->whereNotNull('account_number')
                    ->where('account_number', '!=', '')
                    ->update(['tenant_account_number' => DB::raw('account_number')]);
            }
            if (Schema::hasColumn('payments', 'external_transaction_reference')) {
                DB::table('payments')
                    ->whereNull('external_transaction_reference')
                    ->whereNotNull('transaction_id')
                    ->where('transaction_id', '!=', '')
                    ->update(['external_transaction_reference' => DB::raw('transaction_id')]);
            }
            if (Schema::hasColumn('payments', 'payer_phone')) {
                DB::table('payments')
                    ->whereNull('payer_phone')
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->update(['payer_phone' => DB::raw('phone')]);
            }
            if (Schema::hasColumn('payments', 'payment_provider')) {
                DB::table('payments')
                    ->whereNull('payment_provider')
                    ->whereNotNull('payment_method')
                    ->update(['payment_provider' => DB::raw("CASE
                        WHEN payment_method LIKE 'coop%' THEN 'coop'
                        WHEN payment_method LIKE 'equity%' THEN 'equity'
                        WHEN payment_method LIKE 'kcb%' THEN 'kcb'
                        WHEN payment_method LIKE 'mpesa%' THEN 'mpesa'
                        ELSE payment_method
                    END")]);
            }
            if (Schema::hasColumn('payments', 'reconciliation_status')) {
                DB::table('payments')
                    ->whereNull('reconciliation_status')
                    ->where('status', 'matched')
                    ->update(['reconciliation_status' => 'matched']);
                DB::table('payments')
                    ->whereNull('reconciliation_status')
                    ->where('status', 'unmatched')
                    ->update(['reconciliation_status' => 'unmatched']);
            }
            if (Schema::hasColumn('payments', 'received_at')) {
                DB::table('payments')
                    ->whereNull('received_at')
                    ->update(['received_at' => DB::raw('COALESCE(transaction_date, created_at)')]);
            }
        }

        // Strengthen uniqueness of existing Ac/No within an agent workspace only.
        // Do not alter values; skip the unique index when duplicates already exist.
        if (Schema::hasTable('pm_tenants') && Schema::hasColumn('pm_tenants', 'account_number')) {
            $duplicateGroups = DB::table('pm_tenants')
                ->select('agent_user_id', 'account_number', DB::raw('COUNT(*) as c'))
                ->whereNotNull('account_number')
                ->where('account_number', '!=', '')
                ->groupBy('agent_user_id', 'account_number')
                ->having('c', '>', 1)
                ->limit(1)
                ->count();

            $indexExists = collect(DB::select('SHOW INDEX FROM pm_tenants'))
                ->contains(fn ($row) => (string) ($row->Key_name ?? '') === 'pm_tenants_agent_account_unique');

            if ($duplicateGroups === 0 && ! $indexExists && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
                Schema::table('pm_tenants', function (Blueprint $table): void {
                    $table->unique(['agent_user_id', 'account_number'], 'pm_tenants_agent_account_unique');
                });
            }
        }

        if (! Schema::hasTable('pm_bank_collection_audit_logs')) {
            Schema::create('pm_bank_collection_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 32);
                $table->string('event', 64);
                $table->string('external_transaction_reference', 128)->nullable();
                $table->string('tenant_account_number', 64)->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->string('outcome', 32);
                $table->text('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index('external_transaction_reference', 'pm_bank_coll_audit_ext_ref_idx');
                $table->index('tenant_account_number', 'pm_bank_coll_audit_acno_idx');
                $table->index('tenant_id', 'pm_bank_coll_audit_tenant_idx');
                $table->index('payment_id', 'pm_bank_coll_audit_payment_idx');
                $table->index(['provider', 'created_at'], 'pm_bank_coll_audit_provider_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_bank_collection_audit_logs')) {
            Schema::dropIfExists('pm_bank_collection_audit_logs');
        }

        if (Schema::hasTable('pm_tenants')) {
            $indexExists = collect(DB::select('SHOW INDEX FROM pm_tenants'))
                ->contains(fn ($row) => (string) ($row->Key_name ?? '') === 'pm_tenants_agent_account_unique');
            if ($indexExists) {
                Schema::table('pm_tenants', function (Blueprint $table): void {
                    $table->dropUnique('pm_tenants_agent_account_unique');
                });
            }
        }

        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            foreach ([
                'reconciliation_notes',
                'reconciliation_status',
                'received_at',
                'payer_phone',
                'payer_name',
                'provider_reference',
                'external_transaction_reference',
                'payment_channel',
                'payment_provider',
                'currency',
                'invoice_id',
                'tenant_account_number',
            ] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
