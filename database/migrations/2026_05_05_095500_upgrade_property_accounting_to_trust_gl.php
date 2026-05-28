<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeChartOfAccounts();
        $this->createAccountingPeriods();
        $this->createJournalBatches();
        $this->upgradeJournalLines();
        $this->createLandlordPayoutTables();
        $this->createTenantDepositTables();
        $this->createInvoiceItemTable();
        $this->upgradeMaintenanceAndSupplierTables();
        $this->upgradePayrollTables();
        $this->upgradeReversalTracking();
        $this->seedPropertyTrustChartAccounts();
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_supplier_payments');
        Schema::dropIfExists('pm_supplier_invoices');
        Schema::dropIfExists('pm_suppliers');
        Schema::dropIfExists('pm_invoice_items');
        Schema::dropIfExists('pm_tenant_deposits');
        Schema::dropIfExists('pm_landlord_payout_items');
        Schema::dropIfExists('pm_landlord_payouts');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_journal_batches');
    }

    private function upgradeChartOfAccounts(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        Schema::table('accounting_chart_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_chart_accounts', 'type')) {
                $table->string('type', 24)->nullable()->after('name');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'normal_balance')) {
                $table->string('normal_balance', 10)->nullable()->after('type');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'agent_user_id')) {
                $table->foreignId('agent_user_id')->nullable()->after('parent_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'is_control_account')) {
                $table->boolean('is_control_account')->default(false)->after('normal_balance');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'module')) {
                $table->string('module', 40)->default('core')->after('is_control_account');
            }
        });

        DB::table('accounting_chart_accounts')
            ->whereNull('type')
            ->update(['type' => DB::raw('account_type')]);

        DB::table('accounting_chart_accounts')
            ->whereNull('normal_balance')
            ->update([
                'normal_balance' => DB::raw("
                    CASE
                        WHEN account_type IN ('asset','expense') THEN 'debit'
                        ELSE 'credit'
                    END
                "),
            ]);
    }

    private function createAccountingPeriods(): void
    {
        if (Schema::hasTable('accounting_periods')) {
            return;
        }

        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agent_user_id', 'name'], 'acct_period_agent_name_uq');
            $table->index(['agent_user_id', 'status'], 'acct_period_agent_status_idx');
        });
    }

    private function createJournalBatches(): void
    {
        if (Schema::hasTable('accounting_journal_batches')) {
            return;
        }

        Schema::create('accounting_journal_batches', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('description', 1000)->nullable();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->string('event_type', 80);
            $table->string('source_key', 191);
            $table->string('status', 20)->default('posted');
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('reversed_from_batch_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'event_type'], 'acct_batch_source_event_uq');
            $table->unique(['source_key'], 'acct_batch_source_key_uq');
            $table->index(['agent_user_id', 'date'], 'acct_batch_agent_date_idx');
        });
    }

    private function upgradeJournalLines(): void
    {
        if (! Schema::hasTable('accounting_journal_lines')) {
            return;
        }

        DB::statement('ALTER TABLE accounting_journal_lines MODIFY accounting_journal_entry_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE accounting_journal_lines MODIFY accounting_chart_account_id BIGINT UNSIGNED NULL');

        Schema::table('accounting_journal_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_journal_lines', 'batch_id')) {
                $table->unsignedBigInteger('batch_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('accounting_journal_lines', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('batch_id');
            }
            if (! Schema::hasColumn('accounting_journal_lines', 'reference')) {
                $table->string('reference', 191)->nullable()->after('credit');
            }
            if (! Schema::hasColumn('accounting_journal_lines', 'property_id')) {
                $table->foreignId('property_id')->nullable()->after('reference')->constrained('properties')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_journal_lines', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('property_id')->constrained('pm_tenants')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_journal_lines', 'landlord_id')) {
                $table->foreignId('landlord_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_journal_lines', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('landlord_id')->constrained('property_units')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_journal_lines', 'agent_user_id')) {
                $table->foreignId('agent_user_id')->nullable()->after('unit_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    private function createLandlordPayoutTables(): void
    {
        if (! Schema::hasTable('pm_landlord_payouts')) {
            Schema::create('pm_landlord_payouts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('total_amount', 14, 2);
                $table->string('status', 20)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_landlord_payout_items')) {
            Schema::create('pm_landlord_payout_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payout_id')->constrained('pm_landlord_payouts')->cascadeOnDelete();
                $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();
            });
        }
    }

    private function createTenantDepositTables(): void
    {
        if (Schema::hasTable('pm_tenant_deposits')) {
            return;
        }

        Schema::create('pm_tenant_deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('pm_tenants')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('held');
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function createInvoiceItemTable(): void
    {
        if (Schema::hasTable('pm_invoice_items')) {
            return;
        }

        Schema::create('pm_invoice_items', function (Blueprint $table): void {
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
            $table->string('source_type', 32)->default('custom');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->index(['pm_invoice_id', 'line_no']);
            $table->index(['source_type', 'source_id']);
        });
    }

    private function upgradeMaintenanceAndSupplierTables(): void
    {
        if (Schema::hasTable('pm_maintenance_jobs')) {
            Schema::table('pm_maintenance_jobs', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_maintenance_jobs', 'expense_borne_by')) {
                    $table->string('expense_borne_by', 20)->default('landlord')->after('quote_amount');
                }
                if (! Schema::hasColumn('pm_maintenance_jobs', 'recoverable')) {
                    $table->boolean('recoverable')->default(false)->after('expense_borne_by');
                }
                if (! Schema::hasColumn('pm_maintenance_jobs', 'deduct_from_landlord')) {
                    $table->boolean('deduct_from_landlord')->default(false)->after('recoverable');
                }
            });
        }

        if (! Schema::hasTable('pm_suppliers')) {
            Schema::create('pm_suppliers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('phone', 40)->nullable();
                $table->string('email')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_supplier_invoices')) {
            Schema::create('pm_supplier_invoices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('supplier_id')->constrained('pm_suppliers')->cascadeOnDelete();
                $table->string('invoice_no')->nullable();
                $table->decimal('amount', 14, 2);
                $table->date('invoice_date');
                $table->string('status', 20)->default('unpaid');
                $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_supplier_payments')) {
            Schema::create('pm_supplier_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('supplier_invoice_id')->constrained('pm_supplier_invoices')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->date('paid_at');
                $table->string('reference')->nullable();
                $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    private function upgradePayrollTables(): void
    {
        if (! Schema::hasTable('accounting_payroll_lines')) {
            return;
        }

        Schema::table('accounting_payroll_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_payroll_lines', 'gross_pay')) {
                $table->decimal('gross_pay', 14, 2)->default(0)->after('employee_id');
            }
            if (! Schema::hasColumn('accounting_payroll_lines', 'deductions')) {
                $table->decimal('deductions', 14, 2)->default(0)->after('gross_pay');
            }
            if (! Schema::hasColumn('accounting_payroll_lines', 'net_pay')) {
                $table->decimal('net_pay', 14, 2)->default(0)->after('deductions');
            }
        });
    }

    private function upgradeReversalTracking(): void
    {
        if (Schema::hasTable('pm_payment_allocations')) {
            Schema::table('pm_payment_allocations', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_payment_allocations', 'is_reversed')) {
                    $table->boolean('is_reversed')->default(false)->after('amount');
                }
                if (! Schema::hasColumn('pm_payment_allocations', 'reversed_by')) {
                    $table->foreignId('reversed_by')->nullable()->after('is_reversed')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_payment_allocations', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable()->after('reversed_by');
                }
                if (! Schema::hasColumn('pm_payment_allocations', 'reversal_reason')) {
                    $table->string('reversal_reason', 500)->nullable()->after('reversed_at');
                }
            });
        }

        if (Schema::hasTable('pm_landlord_ledger_entries')) {
            Schema::table('pm_landlord_ledger_entries', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_landlord_ledger_entries', 'agent_user_id')) {
                    $table->foreignId('agent_user_id')->nullable()->after('property_id')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_landlord_ledger_entries', 'reversal_of_id')) {
                    $table->foreignId('reversal_of_id')->nullable()->after('reference_id')->constrained('pm_landlord_ledger_entries')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_landlord_ledger_entries', 'reversed_by')) {
                    $table->foreignId('reversed_by')->nullable()->after('reversal_of_id')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_landlord_ledger_entries', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable()->after('reversed_by');
                }
            });
        }

        if (Schema::hasTable('pm_payments')) {
            Schema::table('pm_payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_payments', 'reversal_status')) {
                    $table->string('reversal_status', 20)->nullable()->after('status');
                }
                if (! Schema::hasColumn('pm_payments', 'reversal_reason')) {
                    $table->string('reversal_reason', 500)->nullable()->after('reversal_status');
                }
                if (! Schema::hasColumn('pm_payments', 'reversal_requested_by')) {
                    $table->foreignId('reversal_requested_by')->nullable()->after('reversal_reason')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_payments', 'reversal_requested_at')) {
                    $table->timestamp('reversal_requested_at')->nullable()->after('reversal_requested_by');
                }
                if (! Schema::hasColumn('pm_payments', 'reversal_approved_by')) {
                    $table->foreignId('reversal_approved_by')->nullable()->after('reversal_requested_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_payments', 'reversal_approved_at')) {
                    $table->timestamp('reversal_approved_at')->nullable()->after('reversal_approved_by');
                }
            });
        }
    }

    private function seedPropertyTrustChartAccounts(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        $rows = [
            ['code' => '1100', 'name' => 'Cash/Bank', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1250', 'name' => 'Suspense (Unidentified Receipts)', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'Landlord Collection Clearing', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2100', 'name' => 'Landlord Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2200', 'name' => 'Tenant Deposit Liability', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2260', 'name' => 'Tenant Credit Liability', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2300', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2350', 'name' => 'Payroll Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2400', 'name' => 'Tax Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '4100', 'name' => 'Rental Income', 'type' => 'income', 'normal_balance' => 'credit'],
            ['code' => '4200', 'name' => 'Management Fee Income', 'type' => 'income', 'normal_balance' => 'credit'],
            ['code' => '4300', 'name' => 'Utility Recovery Income', 'type' => 'income', 'normal_balance' => 'credit'],
            ['code' => '4400', 'name' => 'Penalty Income', 'type' => 'income', 'normal_balance' => 'credit'],
            ['code' => '5101', 'name' => 'Maintenance Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5201', 'name' => 'Payroll Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5301', 'name' => 'Bad Debt Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('accounting_chart_accounts')
                ->where('code', $row['code'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('accounting_chart_accounts')->insert([
                'code' => $row['code'],
                'name' => $row['name'],
                'account_type' => $row['type'],
                'type' => $row['type'],
                'normal_balance' => $row['normal_balance'],
                'is_cash_account' => $row['code'] === '1100',
                'is_control_account' => in_array($row['code'], ['1200', '2100', '2200', '2300'], true),
                'module' => 'property',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};

