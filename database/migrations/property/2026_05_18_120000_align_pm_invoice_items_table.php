<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Older trust-GL migration created pm_invoice_items with `invoice_id`.
 * Invoice lifecycle code expects `pm_invoice_id` plus line-item columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_invoice_items')) {
            $this->createFullInvoiceItemsTable();

            return;
        }

        if (
            Schema::hasColumn('pm_invoice_items', 'invoice_id')
            && ! Schema::hasColumn('pm_invoice_items', 'pm_invoice_id')
        ) {
            $this->dropInvoiceIdForeignKey();

            DB::statement('ALTER TABLE `pm_invoice_items` CHANGE `invoice_id` `pm_invoice_id` BIGINT UNSIGNED NOT NULL');

            Schema::table('pm_invoice_items', function (Blueprint $table) {
                $table->foreign('pm_invoice_id')->references('id')->on('pm_invoices')->cascadeOnDelete();
            });
        }

        Schema::table('pm_invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_invoice_items', 'line_no')) {
                $table->unsignedSmallInteger('line_no')->default(1)->after('pm_invoice_id');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'description')) {
                $table->string('description', 255)->default('')->after('line_no');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'quantity')) {
                $table->decimal('quantity', 14, 3)->default(1)->after('description');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'unit_price')) {
                $table->decimal('unit_price', 14, 2)->default(0)->after('quantity');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'line_subtotal')) {
                $table->decimal('line_subtotal', 14, 2)->default(0)->after('unit_price');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'discount_pct')) {
                $table->decimal('discount_pct', 6, 3)->default(0)->after('line_subtotal');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_pct');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'tax_pct')) {
                $table->decimal('tax_pct', 6, 3)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'tax_amount')) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('tax_pct');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'line_total')) {
                $table->decimal('line_total', 14, 2)->default(0)->after('tax_amount');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'source_type')) {
                $table->string('source_type', 32)->default('custom')->after('line_total');
            }
            if (! Schema::hasColumn('pm_invoice_items', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });

        $this->backfillLegacyInvoiceItemRows();
    }

    public function down(): void
    {
        // Non-destructive upgrade; do not drop columns on rollback.
    }

    private function createFullInvoiceItemsTable(): void
    {
        Schema::create('pm_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_invoice_id')->constrained('pm_invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->string('description', 255);
            $table->decimal('quantity', 14, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2)->default(0);
            $table->decimal('discount_pct', 6, 3)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_pct', 6, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('source_type', 32)->default('custom');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->index(['pm_invoice_id', 'line_no']);
            $table->index(['source_type', 'source_id']);
        });
    }

    private function dropInvoiceIdForeignKey(): void
    {
        $database = Schema::getConnection()->getDatabaseName();
        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'pm_invoice_items')
            ->where('COLUMN_NAME', 'invoice_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->unique();

        foreach ($constraints as $constraint) {
            DB::statement('ALTER TABLE `pm_invoice_items` DROP FOREIGN KEY `'.$constraint.'`');
        }
    }

    private function backfillLegacyInvoiceItemRows(): void
    {
        if (! Schema::hasColumn('pm_invoice_items', 'description')) {
            return;
        }

        $hasType = Schema::hasColumn('pm_invoice_items', 'type');
        $hasAmount = Schema::hasColumn('pm_invoice_items', 'amount');

        if (! $hasType && ! $hasAmount) {
            return;
        }

        $rows = DB::table('pm_invoice_items')->get();
        foreach ($rows as $row) {
            $updates = [];

            $description = trim((string) ($row->description ?? ''));
            if ($description === '' && $hasType) {
                $updates['description'] = (string) ($row->type ?? 'Line item');
            }

            $lineTotal = (float) ($row->line_total ?? 0);
            if ($lineTotal <= 0 && $hasAmount) {
                $lineTotal = (float) ($row->amount ?? 0);
                $updates['line_total'] = $lineTotal;
                $updates['line_subtotal'] = $lineTotal;
                $updates['unit_price'] = $lineTotal;
                $updates['quantity'] = 1;
            }

            if (Schema::hasColumn('pm_invoice_items', 'source_type')) {
                $sourceType = (string) ($row->source_type ?? '');
                if ($sourceType === '' && $hasType) {
                    $updates['source_type'] = (string) ($row->type ?? 'custom');
                }
            }

            if ($updates !== []) {
                DB::table('pm_invoice_items')->where('id', $row->id)->update($updates);
            }
        }
    }
};
