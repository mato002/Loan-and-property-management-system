<?php
require __DIR__."/vendor/autoload.php";
$app = require __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

$year = (int) Carbon::now()->year;
$month = (int) Carbon::now()->month;
$landlordId = DB::table('property_landlord')->value('user_id');
$propertyIds = DB::table('property_landlord')->where('user_id',$landlordId)->pluck('property_id')->all();
$unitIds = DB::table('property_units')->whereIn('property_id',$propertyIds)->pluck('id')->all();

$allocSumYearMonth = DB::table('pm_payment_allocations as a')
  ->join('pm_payments as p','p.id','=','a.pm_payment_id')
  ->join('pm_invoices as i','i.id','=','a.pm_invoice_id')
  ->where('p.status','completed')
  ->whereIn('i.property_unit_id',$unitIds)
  ->whereRaw('YEAR(COALESCE(p.paid_at, p.created_at)) = ?', [$year])
  ->whereRaw('MONTH(COALESCE(p.paid_at, p.created_at)) = ?', [$month])
  ->sum('a.amount');

echo "allocSum_yearMonth_expr={$allocSumYearMonth}\n";
