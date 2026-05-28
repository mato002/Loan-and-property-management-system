<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('utility_audit_logs')) {
            Schema::create('utility_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 64);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('action', 64);
                $table->string('billing_month', 7)->nullable();
                $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
                $table->foreignId('pm_tenant_id')->nullable()->constrained('pm_tenants')->nullOnDelete();
                $table->foreignId('pm_invoice_id')->nullable()->constrained('pm_invoices')->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('payload')->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                $table->index(['entity_type', 'entity_id']);
                $table->index(['billing_month', 'action']);
                $table->index('property_unit_id');
                $table->index('created_at');
            });
        }

        if (Schema::hasTable('pm_water_readings') && ! Schema::hasColumn('pm_water_readings', 'is_estimated')) {
            Schema::table('pm_water_readings', function (Blueprint $table) {
                $table->boolean('is_estimated')->default(false)->after('status');
                $table->boolean('is_meter_reset')->default(false)->after('is_estimated');
            });
        }

        if (Schema::hasTable('pm_invoice_penalty_applications') && ! Schema::hasColumn('pm_invoice_penalty_applications', 'reversed_at')) {
            Schema::table('pm_invoice_penalty_applications', function (Blueprint $table) {
                $table->timestamp('reversed_at')->nullable()->after('applied_at');
                $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
                $table->string('reversal_reason', 500)->nullable()->after('reversed_by');
            });
        }

        $this->seedUtilityChartAccounts();
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_invoice_penalty_applications')) {
            Schema::table('pm_invoice_penalty_applications', function (Blueprint $table) {
                if (Schema::hasColumn('pm_invoice_penalty_applications', 'reversed_by')) {
                    $table->dropConstrainedForeignId('reversed_by');
                }
                foreach (['reversed_at', 'reversal_reason'] as $col) {
                    if (Schema::hasColumn('pm_invoice_penalty_applications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('pm_water_readings')) {
            Schema::table('pm_water_readings', function (Blueprint $table) {
                foreach (['is_estimated', 'is_meter_reset'] as $col) {
                    if (Schema::hasColumn('pm_water_readings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('utility_audit_logs');

        if (Schema::hasTable('accounting_chart_accounts')) {
            foreach (['1210', '4310', '4410'] as $code) {
                DB::table('accounting_chart_accounts')->where('code', $code)->where('module', 'property')->delete();
            }
        }
    }

    private function seedUtilityChartAccounts(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        $rows = [
            ['code' => '1210', 'name' => 'Utility Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit', 'control' => true],
            ['code' => '4310', 'name' => 'Water Revenue', 'type' => 'income', 'normal_balance' => 'credit', 'control' => false],
            ['code' => '4410', 'name' => 'Utility Penalty Income', 'type' => 'income', 'normal_balance' => 'credit', 'control' => false],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('accounting_chart_accounts')->where('code', $row['code'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('accounting_chart_accounts')->insert([
                'code' => $row['code'],
                'name' => $row['name'],
                'account_type' => $row['type'],
                'type' => $row['type'],
                'normal_balance' => $row['normal_balance'],
                'is_cash_account' => false,
                'is_control_account' => $row['control'],
                'module' => 'property',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
