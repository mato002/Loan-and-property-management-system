<?php

namespace App\Console\Commands;

use App\Services\Property\LandlordSubledgerService;
use Illuminate\Console\Command;

class ReconcileLandlordSubledger extends Command
{
    protected $signature = 'finance:reconcile-landlord-subledger
                            {--property= : Limit to one properties.id}';

    protected $description = 'Report GL 2100 landlord payable vs landlord subledger drift and duplicate owner credits.';

    public function handle(LandlordSubledgerService $subledger): int
    {
        $propertyId = $this->option('property');
        $propertyId = $propertyId !== null && $propertyId !== '' ? (int) $propertyId : null;

        $drift = $subledger->reconcileGl2100VsSubledger($propertyId, 100);
        $duplicates = $subledger->detectDuplicateCredits(100);
        $gaps = $subledger->detectGaps(null, 100);

        $this->line('Landlord ledger gaps: '.$gaps->count());
        $this->line('Duplicate owner credits: '.$duplicates->count());
        $this->line('GL 2100 vs subledger drift rows: '.$drift->count());

        if ($drift->isNotEmpty()) {
            $this->newLine();
            $this->warn('GL 2100 vs subledger drift:');
            $this->table(
                ['Property', 'GL 2100 net', 'Subledger net', 'Drift'],
                $drift->map(fn (array $row) => [
                    (string) $row['property_id'],
                    number_format((float) $row['gl_2100_net'], 2),
                    number_format((float) $row['subledger_net'], 2),
                    number_format((float) $row['drift'], 2),
                ])->all()
            );
        }

        if ($duplicates->isNotEmpty()) {
            $this->newLine();
            $this->warn('Duplicate owner credits:');
            foreach ($duplicates as $row) {
                $this->line('- '.(string) ($row['message'] ?? ''));
            }
        }

        $issueCount = $gaps->count() + $duplicates->count() + $drift->count();

        return $issueCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
