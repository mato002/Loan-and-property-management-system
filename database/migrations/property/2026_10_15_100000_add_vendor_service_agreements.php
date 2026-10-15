<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_vendors')) {
            Schema::table('pm_vendors', function (Blueprint $table): void {
                foreach ([
                    'registration_no' => fn () => $table->string('registration_no', 100)->nullable(),
                    'bank_name' => fn () => $table->string('bank_name', 150)->nullable(),
                    'bank_branch_code' => fn () => $table->string('bank_branch_code', 80)->nullable(),
                    'bank_account_name' => fn () => $table->string('bank_account_name', 191)->nullable(),
                    'bank_account_number' => fn () => $table->string('bank_account_number', 100)->nullable(),
                    'creditor_account' => fn () => $table->string('creditor_account', 120)->nullable(),
                ] as $column => $addColumn) {
                    if (! Schema::hasColumn('pm_vendors', $column)) {
                        $addColumn();
                    }
                }
                if (! Schema::hasColumn('pm_vendors', 'default_property_id')) {
                    $table->foreignId('default_property_id')->nullable()->constrained('properties')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('pm_vendor_service_agreements')) {
            Schema::create('pm_vendor_service_agreements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pm_vendor_id')->constrained('pm_vendors')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->string('service_name', 191);
                $table->decimal('monthly_amount', 14, 2);
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->unsignedTinyInteger('billing_day')->default(1);
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['agent_user_id', 'status', 'starts_on'], 'pm_vendor_agreements_agent_status_start_idx');
            });
        }

        if (! Schema::hasTable('pm_property_expense_requisitions')) {
            Schema::create('pm_property_expense_requisitions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pm_vendor_id')->constrained('pm_vendors')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('vendor_invoice_no', 191)->nullable();
                $table->date('requested_date');
                $table->date('due_date')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('status', 20)->default('pending');
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('pm_ezen_bill_id')->nullable()->constrained('pm_ezen_bills')->nullOnDelete();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['agent_user_id', 'status', 'requested_date'], 'pm_expense_reqs_agent_status_date_idx');
            });
        }

        if (Schema::hasTable('pm_ezen_bills')) {
            Schema::table('pm_ezen_bills', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_ezen_bills', 'property_id')) {
                    $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_ezen_bills', 'landlord_id')) {
                    $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('pm_ezen_bills', 'pm_vendor_service_agreement_id')) {
                    $table->foreignId('pm_vendor_service_agreement_id', 'pm_ezen_bills_service_agreement_fk')
                        ->nullable()->constrained('pm_vendor_service_agreements')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('pm_ezen_payment_vouchers')
            && ! Schema::hasColumn('pm_ezen_payment_vouchers', 'pm_ezen_bill_id')) {
            Schema::table('pm_ezen_payment_vouchers', function (Blueprint $table): void {
                $table->foreignId('pm_ezen_bill_id')->nullable()->constrained('pm_ezen_bills')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_expense_requisitions');
        if (Schema::hasTable('pm_ezen_bills')) {
            Schema::table('pm_ezen_bills', function (Blueprint $table): void {
                if (Schema::hasColumn('pm_ezen_bills', 'pm_vendor_service_agreement_id')) {
                    $table->dropForeign('pm_ezen_bills_service_agreement_fk');
                    $table->dropColumn('pm_vendor_service_agreement_id');
                }
                foreach (['landlord_id', 'property_id'] as $column) {
                    if (Schema::hasColumn('pm_ezen_bills', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }
        if (Schema::hasTable('pm_ezen_payment_vouchers') && Schema::hasColumn('pm_ezen_payment_vouchers', 'pm_ezen_bill_id')) {
            Schema::table('pm_ezen_payment_vouchers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pm_ezen_bill_id');
            });
        }
        Schema::dropIfExists('pm_vendor_service_agreements');
        if (Schema::hasTable('pm_vendors')) {
            Schema::table('pm_vendors', function (Blueprint $table): void {
                if (Schema::hasColumn('pm_vendors', 'default_property_id')) {
                    $table->dropConstrainedForeignId('default_property_id');
                }
                $columns = ['registration_no', 'bank_name', 'bank_branch_code', 'bank_account_name', 'bank_account_number', 'creditor_account'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('pm_vendors', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
