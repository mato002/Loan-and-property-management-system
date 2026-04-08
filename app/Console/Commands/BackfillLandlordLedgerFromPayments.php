<?php

namespace App\Console\Commands;

use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Services\Property\LandlordLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillLandlordLedgerFromPayments extends Command
{
    protected $signature = 'property:backfill-landlord-ledger
        {--from= : Start date (YYYY-MM-DD), defaults to 90 days ago}
        {--to= : End date (YYYY-MM-DD), defaults to today}
        {--limit=5000 : Max payments to scan}
        {--dry-run : Show what would be posted without writing}';

    protected $description = 'Backfill landlord ledger credits from completed tenant payments (net of commission, split by ownership shares).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $fromOpt = trim((string) $this->option('from'));
        $toOpt = trim((string) $this->option('to'));

        $from = $fromOpt !== '' ? Carbon::parse($fromOpt)->startOfDay() : now()->subDays(90)->startOfDay();
        $to = $toOpt !== '' ? Carbon::parse($toOpt)->endOfDay() : now()->endOfDay();

        $defaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $defaultPct = is_numeric($defaultRaw) ? (float) $defaultRaw : 10.0;
        $defaultPct = max(0.0, $defaultPct);

        $overrideRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = json_decode($overrideRaw, true);
        $overrides = is_array($overrides) ? $overrides : [];

        $payments = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('paid_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $stats = [
            'scanned' => 0,
            'skipped_existing' => 0,
            'skipped_no_alloc' => 0,
            'posted_entries' => 0,
            'posted_total' => 0.0,
        ];

        foreach ($payments as $payment) {
            $stats['scanned']++;

            $already = PmLandlordLedgerEntry::query()
                ->where('reference_type', 'pm_payment')
                ->where('reference_id', $payment->id)
                ->exists();
            if ($already) {
                $stats['skipped_existing']++;
                continue;
            }

            $allocs = DB::table('pm_payment_allocations as a')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
                ->where('a.pm_payment_id', $payment->id)
                ->get(['a.amount', 'u.property_id']);

            if ($allocs->isEmpty()) {
                $stats['skipped_no_alloc']++;
                continue;
            }

            $byProperty = [];
            foreach ($allocs as $a) {
                $pid = (int) ($a->property_id ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $byProperty[$pid] = ($byProperty[$pid] ?? 0.0) + (float) $a->amount;
            }
            if ($byProperty === []) {
                $stats['skipped_no_alloc']++;
                continue;
            }

            $paymentRef = $payment->external_ref ?: ('PAY-'.$payment->id);
            $occurredAt = $payment->paid_at ?? $payment->created_at ?? now();

            foreach ($byProperty as $propertyId => $grossCollected) {
                $commissionPct = is_numeric($overrides[(string) $propertyId] ?? null)
                    ? max(0.0, (float) $overrides[(string) $propertyId])
                    : $defaultPct;
                $commission = $grossCollected * ($commissionPct / 100);
                $netToOwners = max(0.0, $grossCollected - $commission);
                if ($netToOwners <= 0) {
                    continue;
                }

                $property = Property::query()->find($propertyId);
                $links = DB::table('property_landlord')->where('property_id', $propertyId)->get(['user_id', 'ownership_percent']);
                foreach ($links as $link) {
                    $uid = (int) ($link->user_id ?? 0);
                    $ownershipPct = (float) ($link->ownership_percent ?? 0);
                    if ($uid <= 0 || $ownershipPct <= 0) {
                        continue;
                    }
                    $share = $netToOwners * ($ownershipPct / 100);
                    if ($share <= 0) {
                        continue;
                    }

                    $user = User::query()->find($uid);
                    if (! $user) {
                        continue;
                    }

                    $desc = 'Rent collected '.$paymentRef.' (net after '.$commissionPct.'% commission)';

                    $this->line(sprintf(
                        '%s %s → user #%d credit %.2f (property #%d, %.2f%% ownership)',
                        $dryRun ? '[dry-run]' : '[post]',
                        $paymentRef,
                        $uid,
                        $share,
                        $propertyId,
                        $ownershipPct
                    ));

                    if (! $dryRun) {
                        LandlordLedger::post(
                            $user,
                            PmLandlordLedgerEntry::DIRECTION_CREDIT,
                            (float) $share,
                            $desc,
                            $property,
                            'pm_payment',
                            (int) $payment->id,
                            $occurredAt
                        );
                    }

                    $stats['posted_entries']++;
                    $stats['posted_total'] += $share;
                }
            }
        }

        $this->info('Backfill complete.');
        $this->table(
            ['from', 'to', 'mode', 'scanned', 'skipped_existing', 'skipped_no_alloc', 'posted_entries', 'posted_total'],
            [[
                $from->toDateString(),
                $to->toDateString(),
                $dryRun ? 'dry-run' : 'write',
                $stats['scanned'],
                $stats['skipped_existing'],
                $stats['skipped_no_alloc'],
                $stats['posted_entries'],
                number_format((float) $stats['posted_total'], 2, '.', ''),
            ]]
        );

        return self::SUCCESS;
    }
}

