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
            if (! Schema::hasColumn('loan_clients', 'biodata_meta')) {
                $table->json('biodata_meta')->nullable()->after('id_back_photo_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        Schema::table('loan_clients', function (Blueprint $table) {
            if (Schema::hasColumn('loan_clients', 'biodata_meta')) {
                $table->dropColumn('biodata_meta');
            }
        });
    }
};
