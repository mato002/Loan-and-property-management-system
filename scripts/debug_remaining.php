<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Property;
use App\Services\Property\PassionLegacyLeasesRegisterParser;
use App\Services\Property\PassionLegacyRegisterPdfTextExtractor;

foreach (['M00033A', 'M00040A', 'M00002A', 'M00024B'] as $code) {
    $p = Property::withoutGlobalScopes()->where('code', $code)->first();
    echo "\n=== {$p->name} [{$code}] ===\n";
    foreach ($p->units()->withoutGlobalScopes()->orderBy('label')->get() as $u) {
        echo "  {$u->label} ({$u->status})\n";
    }
    $records = array_filter(
        app(PassionLegacyLeasesRegisterParser::class)->parse(app(PassionLegacyRegisterPdfTextExtractor::class)->extract(storage_path('passion-legacy/leases.txt'))),
        fn ($r) => $r['property_code'] === $code
    );
    foreach ($records as $r) {
        echo "  lease: {$r['unit_label']} | {$r['tenant_name']} | {$r['account_number']}\n";
    }
}
