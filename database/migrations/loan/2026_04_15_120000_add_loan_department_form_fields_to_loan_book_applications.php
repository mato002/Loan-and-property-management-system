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

        Schema::table('loan_book_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_book_applications', 'applicant_pin_location_code')) {
                $table->string('applicant_pin_location_code', 120)->nullable();
                $table->boolean('repayment_agreement_accepted')->default(false);
                $table->string('applicant_signature_name', 200)->nullable();
                $table->string('guarantor_full_name', 200)->nullable();
                $table->string('guarantor_id_number', 80)->nullable();
                $table->string('guarantor_phone', 40)->nullable();
                $table->string('guarantor_signature_name', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return;
        }

        Schema::table('loan_book_applications', function (Blueprint $table) {
            foreach ([
                'applicant_pin_location_code',
                'repayment_agreement_accepted',
                'applicant_signature_name',
                'guarantor_full_name',
                'guarantor_id_number',
                'guarantor_phone',
                'guarantor_signature_name',
            ] as $col) {
                if (Schema::hasColumn('loan_book_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
