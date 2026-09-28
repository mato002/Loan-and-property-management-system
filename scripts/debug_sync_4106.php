<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Support\Property\PmPaymentPresentation;

$p = PmPayment::withoutGlobalScopes()->find(4106);
$t = PmTenant::withoutGlobalScopes()->find($p->pm_tenant_id);
echo "channel={$p->channel} agent={$t->agent_user_id}\n";
echo "needs=". (PmPaymentPresentation::isInternalEzenReference((string)$p->external_ref) ? 'yes':'no') ."\n";
echo "meta mpesa=".data_get($p->meta,'mpesa_ref','')." receipted=".data_get($p->meta,'receipted_to','')."\n";

$svc = app(\App\Services\Property\EzenRentReceiptsImportService::class);
$r = $svc->syncPaymentsFromRegister(2, false);
echo json_encode($r)."\n";

$p->refresh();
echo "after: mpesa=".data_get($p->meta,'mpesa_ref','')." receipted=".data_get($p->meta,'receipted_to','')."\n";
