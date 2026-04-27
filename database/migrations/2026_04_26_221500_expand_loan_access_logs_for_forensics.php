<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_access_logs')) {
            return;
        }

        Schema::table('loan_access_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_access_logs', 'session_id')) {
                $table->string('session_id', 255)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('loan_access_logs', 'device_fingerprint')) {
                $table->string('device_fingerprint', 120)->nullable()->after('session_id');
            }
            if (! Schema::hasColumn('loan_access_logs', 'event_category')) {
                $table->string('event_category', 32)->nullable()->after('method');
                $table->index('event_category');
            }
            if (! Schema::hasColumn('loan_access_logs', 'action_type')) {
                $table->string('action_type', 32)->nullable()->after('event_category');
                $table->index('action_type');
            }
            if (! Schema::hasColumn('loan_access_logs', 'result')) {
                $table->string('result', 24)->nullable()->after('activity');
                $table->index('result');
            }
            if (! Schema::hasColumn('loan_access_logs', 'risk_score')) {
                $table->unsignedTinyInteger('risk_score')->nullable()->after('result');
                $table->index('risk_score');
            }
            if (! Schema::hasColumn('loan_access_logs', 'risk_level')) {
                $table->string('risk_level', 16)->nullable()->after('risk_score');
                $table->index('risk_level');
            }
            if (! Schema::hasColumn('loan_access_logs', 'risk_reason')) {
                $table->string('risk_reason', 500)->nullable()->after('risk_level');
            }
            if (! Schema::hasColumn('loan_access_logs', 'requires_reason')) {
                $table->boolean('requires_reason')->default(false)->after('risk_reason');
            }
            if (! Schema::hasColumn('loan_access_logs', 'reason_text')) {
                $table->text('reason_text')->nullable()->after('requires_reason');
            }
            if (! Schema::hasColumn('loan_access_logs', 'old_value')) {
                $table->json('old_value')->nullable()->after('reason_text');
            }
            if (! Schema::hasColumn('loan_access_logs', 'new_value')) {
                $table->json('new_value')->nullable()->after('old_value');
            }
            if (! Schema::hasColumn('loan_access_logs', 'audit_token')) {
                $table->string('audit_token', 32)->nullable()->after('new_value');
                $table->index('audit_token');
            }
            if (! Schema::hasColumn('loan_access_logs', 'checksum')) {
                $table->string('checksum', 128)->nullable()->after('audit_token');
                $table->index('checksum');
            }
            if (! Schema::hasColumn('loan_access_logs', 'previous_hash')) {
                $table->string('previous_hash', 128)->nullable()->after('checksum');
            }
            if (! Schema::hasColumn('loan_access_logs', 'mfa_verified')) {
                $table->boolean('mfa_verified')->nullable()->after('previous_hash');
            }
            if (! Schema::hasColumn('loan_access_logs', 'country_code')) {
                $table->string('country_code', 8)->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('loan_access_logs', 'geo_label')) {
                $table->string('geo_label', 120)->nullable()->after('country_code');
            }
            if (! Schema::hasColumn('loan_access_logs', 'is_foreign_ip')) {
                $table->boolean('is_foreign_ip')->default(false)->after('geo_label');
            }
            if (! Schema::hasColumn('loan_access_logs', 'is_privileged')) {
                $table->boolean('is_privileged')->default(false)->after('is_foreign_ip');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_access_logs')) {
            return;
        }

        Schema::table('loan_access_logs', function (Blueprint $table): void {
            $columns = [
                'session_id',
                'device_fingerprint',
                'event_category',
                'action_type',
                'result',
                'risk_score',
                'risk_level',
                'risk_reason',
                'requires_reason',
                'reason_text',
                'old_value',
                'new_value',
                'audit_token',
                'checksum',
                'previous_hash',
                'mfa_verified',
                'country_code',
                'geo_label',
                'is_foreign_ip',
                'is_privileged',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('loan_access_logs', $column)) {
                    if (in_array($column, ['event_category', 'action_type', 'result', 'risk_score', 'risk_level', 'audit_token', 'checksum'], true)) {
                        $table->dropIndex([$column]);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
