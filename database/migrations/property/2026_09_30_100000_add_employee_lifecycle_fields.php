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
            if (! Schema::hasColumn('employees', 'probation_ends_on')) {
                $table->date('probation_ends_on')->nullable();
            }
            if (! Schema::hasColumn('employees', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable();
            }
            if (! Schema::hasColumn('employees', 'exit_date')) {
                $table->date('exit_date')->nullable();
            }
            if (! Schema::hasColumn('employees', 'exit_reason')) {
                $table->string('exit_reason', 80)->nullable();
            }
            if (! Schema::hasColumn('employees', 'offboarding_notes')) {
                $table->text('offboarding_notes')->nullable();
            }
            if (! Schema::hasColumn('employees', 'offboarded_by_user_id')) {
                $table->unsignedBigInteger('offboarded_by_user_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            foreach ([
                'probation_ends_on',
                'onboarding_completed_at',
                'exit_date',
                'exit_reason',
                'offboarding_notes',
                'offboarded_by_user_id',
            ] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
