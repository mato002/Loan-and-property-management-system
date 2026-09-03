<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = Illuminate\Support\Facades\Schema::getColumnListing('accounting_journal_entries');
echo implode("\n", $cols).PHP_EOL;
echo 'has status: '.(Illuminate\Support\Facades\Schema::hasColumn('accounting_journal_entries', 'status') ? 'yes' : 'no').PHP_EOL;
