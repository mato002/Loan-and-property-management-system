<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return;
        }

        Schema::table('loan_book_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_book_applications', 'form_meta')) {
                $table->json('form_meta')->nullable()->after('guarantor_signature_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return;
        }

        Schema::table('loan_book_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_book_applications', 'form_meta')) {
                $table->dropColumn('form_meta');
            }
        });
    }
};

