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
            if (! Schema::hasColumn('loan_clients', 'gender')) {
                $table->string('gender', 20)->nullable()->after('id_number');
            }
            if (! Schema::hasColumn('loan_clients', 'next_of_kin_name')) {
                $table->string('next_of_kin_name', 200)->nullable()->after('gender');
            }
            if (! Schema::hasColumn('loan_clients', 'next_of_kin_contact')) {
                $table->string('next_of_kin_contact', 40)->nullable()->after('next_of_kin_name');
            }
            if (! Schema::hasColumn('loan_clients', 'client_photo_path')) {
                $table->string('client_photo_path', 255)->nullable()->after('next_of_kin_contact');
            }
            if (! Schema::hasColumn('loan_clients', 'id_front_photo_path')) {
                $table->string('id_front_photo_path', 255)->nullable()->after('client_photo_path');
            }
            if (! Schema::hasColumn('loan_clients', 'id_back_photo_path')) {
                $table->string('id_back_photo_path', 255)->nullable()->after('id_front_photo_path');
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
                'gender',
                'next_of_kin_name',
                'next_of_kin_contact',
                'client_photo_path',
                'id_front_photo_path',
                'id_back_photo_path',
            ] as $col) {
                if (Schema::hasColumn('loan_clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
