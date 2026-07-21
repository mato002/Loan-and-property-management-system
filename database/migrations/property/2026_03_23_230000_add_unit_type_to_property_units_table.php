<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->string('unit_type', 32)->default('apartment')->after('label');
        });

        DB::table('property_units')
            ->whereNull('unit_type')
            ->update(['unit_type' => 'apartment']);
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};

