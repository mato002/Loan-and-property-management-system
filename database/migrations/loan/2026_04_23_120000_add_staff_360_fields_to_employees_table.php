<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'employment_status')) {
                $table->string('employment_status', 40)->nullable()->after('job_title');
            }
            if (! Schema::hasColumn('employees', 'work_type')) {
                $table->string('work_type', 40)->nullable()->after('employment_status');
            }
            if (! Schema::hasColumn('employees', 'gender')) {
                $table->string('gender', 20)->nullable()->after('work_type');
            }
            if (! Schema::hasColumn('employees', 'national_id')) {
                $table->string('national_id', 40)->nullable()->after('gender');
            }
            if (! Schema::hasColumn('employees', 'personal_email')) {
                $table->string('personal_email', 255)->nullable()->after('email');
            }
            if (! Schema::hasColumn('employees', 'next_of_kin_name')) {
                $table->string('next_of_kin_name', 200)->nullable()->after('personal_email');
            }
            if (! Schema::hasColumn('employees', 'next_of_kin_phone')) {
                $table->string('next_of_kin_phone', 40)->nullable()->after('next_of_kin_name');
            }
            if (! Schema::hasColumn('employees', 'supervisor_employee_id')) {
                $table->foreignId('supervisor_employee_id')->nullable()->after('branch')->constrained('employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'assigned_tools')) {
                $table->text('assigned_tools')->nullable()->after('supervisor_employee_id');
            }
            if (! Schema::hasColumn('employees', 'kra_pin')) {
                $table->string('kra_pin', 30)->nullable()->after('assigned_tools');
            }
            if (! Schema::hasColumn('employees', 'bank_name')) {
                $table->string('bank_name', 120)->nullable()->after('kra_pin');
            }
            if (! Schema::hasColumn('employees', 'bank_account_number')) {
                $table->string('bank_account_number', 80)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('employees', 'nhif_number')) {
                $table->string('nhif_number', 40)->nullable()->after('bank_account_number');
            }
            if (! Schema::hasColumn('employees', 'nssf_number')) {
                $table->string('nssf_number', 40)->nullable()->after('nhif_number');
            }
            if (! Schema::hasColumn('employees', 'employment_contract_scan')) {
                $table->string('employment_contract_scan', 255)->nullable()->after('nssf_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            if (Schema::hasColumn('employees', 'supervisor_employee_id')) {
                $table->dropConstrainedForeignId('supervisor_employee_id');
            }

            $columns = [
                'employment_status',
                'work_type',
                'gender',
                'national_id',
                'personal_email',
                'next_of_kin_name',
                'next_of_kin_phone',
                'assigned_tools',
                'kra_pin',
                'bank_name',
                'bank_account_number',
                'nhif_number',
                'nssf_number',
                'employment_contract_scan',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
