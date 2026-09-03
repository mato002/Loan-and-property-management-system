<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$row = DB::table('migrations')
    ->where('migration', 'like', '%add_status_and_reversal_fields_to_accounting_journal_entries%')
    ->first();

echo 'migration record: '.($row ? json_encode($row) : 'none').PHP_EOL;

if (! Schema::hasTable('accounting_journal_entries')) {
    echo "table missing\n";
    exit(1);
}

Schema::table('accounting_journal_entries', function (Blueprint $table): void {
    if (! Schema::hasColumn('accounting_journal_entries', 'status')) {
        $table->string('status', 20)->default('posted')->after('description');
        echo "added status\n";
    }
    if (! Schema::hasColumn('accounting_journal_entries', 'approved_by')) {
        $table->unsignedBigInteger('approved_by')->nullable()->after('created_by');
        echo "added approved_by\n";
    }
    if (! Schema::hasColumn('accounting_journal_entries', 'approved_at')) {
        $table->dateTime('approved_at')->nullable()->after('approved_by');
        echo "added approved_at\n";
    }
    if (! Schema::hasColumn('accounting_journal_entries', 'reversed_from_id')) {
        $table->unsignedBigInteger('reversed_from_id')->nullable()->after('approved_at');
        echo "added reversed_from_id\n";
    }
});

$cols = Schema::getColumnListing('accounting_journal_entries');
echo 'columns now: '.implode(', ', $cols).PHP_EOL;
echo 'has status: '.(Schema::hasColumn('accounting_journal_entries', 'status') ? 'yes' : 'no').PHP_EOL;
