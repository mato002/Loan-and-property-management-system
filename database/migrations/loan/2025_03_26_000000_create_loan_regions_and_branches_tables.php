<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_regions')) {
            Schema::create('loan_regions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->nullable()->unique();
                $table->string('name');
                $table->string('description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_branches')) {
            Schema::create('loan_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_region_id')->constrained('loan_regions')->restrictOnDelete();
                $table->string('code', 40)->nullable()->unique();
                $table->string('name');
                $table->string('address', 500)->nullable();
                $table->string('phone', 60)->nullable();
                $table->string('manager_name', 160)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['loan_region_id', 'is_active']);
            });
        }

        if (Schema::hasTable('loan_book_loans') && ! Schema::hasColumn('loan_book_loans', 'loan_branch_id')) {
            Schema::table('loan_book_loans', function (Blueprint $table) {
                $table->foreignId('loan_branch_id')->nullable()->after('branch')->constrained('loan_branches')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_book_loans') && Schema::hasColumn('loan_book_loans', 'loan_branch_id')) {
            Schema::table('loan_book_loans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('loan_branch_id');
            });
        }

        Schema::dropIfExists('loan_branches');
        Schema::dropIfExists('loan_regions');
    }
};
