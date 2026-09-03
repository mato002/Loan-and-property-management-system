<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Services\Property\PassionLegacyLeasesRegisterParser;
use App\Services\Property\PassionLegacyRegisterPdfTextExtractor;
use App\Services\Property\PassionPropertyCodeResolver;

$parser = app(PassionLegacyLeasesRegisterParser::class);
$extractor = app(PassionLegacyRegisterPdfTextExtractor::class);
$records = $parser->parse($extractor->extract(storage_path('passion-legacy/leases.txt')));

foreach ($records as $r) {
    if ($r['property_code'] !== 'M00001A') {
        continue;
    }
    $t = PmTenant::withoutGlobalScopes()->where('account_number', strtoupper($r['account_number']))->first();
    $lease = $t ? PmLease::withoutGlobalScopes()->where('pm_tenant_id', $t->id)->where('status', 'active')->with('units')->first() : null;
    $unit = $lease?->units->first();
    echo "{$r['unit_label']} {$r['account_number']} tenant=".($t?->name ?? 'MISSING').' unit='.($unit?->label ?? '-')." id=".($unit?->id ?? '-')."\n";
}
