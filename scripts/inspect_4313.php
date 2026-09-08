<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmPayment;

$p = PmPayment::withoutGlobalScopes()->with('allocations.invoice.unit')->find(4313);
echo "PAY-4313 amt={$p->amount} paid={$p->paid_at} unit=".($p->allocations->first()?->invoice?->unit?->label ?? '?')."\n";
echo json_encode($p->meta)."\n";
