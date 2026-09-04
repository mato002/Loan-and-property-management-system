<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table): void {
                if (! Schema::hasColumn('employees', 'user_id')) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('employees', 'agent_user_id')) {
                    $table->foreignId('agent_user_id')
                        ->nullable()
                        ->after('user_id')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('pm_field_officers') && Schema::hasTable('employees')) {
            Schema::table('pm_field_officers', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_field_officers', 'employee_id')) {
                    $table->foreignId('employee_id')
                        ->nullable()
                        ->after('agent_user_id')
                        ->constrained('employees')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_field_officers') && Schema::hasColumn('pm_field_officers', 'employee_id')) {
            Schema::table('pm_field_officers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('employee_id');
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table): void {
                if (Schema::hasColumn('employees', 'agent_user_id')) {
                    $table->dropConstrainedForeignId('agent_user_id');
                }
                if (Schema::hasColumn('employees', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
            });
        }
    }
};
