<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        Schema::table('loan_clients', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_clients', 'guarantor_1_full_name')) {
                $table->string('guarantor_1_full_name', 200)->nullable();
                $table->string('guarantor_1_phone', 40)->nullable();
                $table->string('guarantor_1_id_number', 80)->nullable();
                $table->string('guarantor_1_relationship', 80)->nullable();
                $table->text('guarantor_1_address')->nullable();
                $table->string('guarantor_2_full_name', 200)->nullable();
                $table->string('guarantor_2_phone', 40)->nullable();
                $table->string('guarantor_2_id_number', 80)->nullable();
                $table->string('guarantor_2_relationship', 80)->nullable();
                $table->text('guarantor_2_address')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        Schema::table('loan_clients', function (Blueprint $table) {
            foreach ([
                'guarantor_1_full_name',
                'guarantor_1_phone',
                'guarantor_1_id_number',
                'guarantor_1_relationship',
                'guarantor_1_address',
                'guarantor_2_full_name',
                'guarantor_2_phone',
                'guarantor_2_id_number',
                'guarantor_2_relationship',
                'guarantor_2_address',
            ] as $col) {
                if (Schema::hasColumn('loan_clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
