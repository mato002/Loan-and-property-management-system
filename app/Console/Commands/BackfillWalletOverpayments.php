<?php

namespace App\Console\Commands;

use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Services\ClientWalletService;
use Illuminate\Console\Command;

class BackfillWalletOverpayments extends Command
{
    protected $signature = 'loan:backfill-wallet-overpayments {--dry-run : Show actions without writing}';

    protected $description = 'Create wallet credit transactions for historical processed payments with overpayment allocations.';

    public function handle(ClientWalletService $wallets): int
    {
        $dry = (bool) $this->option('dry-run');

        $payments = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->whereHas('allocations', fn ($q) => $q->where('component', 'overpayment')->where('amount', '>', 0))
            ->with(['allocations', 'loan.loanClient'])
            ->orderBy('id')
            ->get();

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($payments as $payment) {
            $client = $payment->loan?->loanClient;
            if (! $client || $client->kind !== LoanClient::KIND_CLIENT) {
                $skipped++;

                continue;
            }

            if ($dry) {
                $this->line("[dry-run] Would sync overpayment wallet for payment #{$payment->id}");
                $processed++;

                continue;
            }

            try {
                $wallets->ensureWallet($client, null);
                $wallets->syncOverpaymentCreditFromPayment($payment, $client);
                $processed++;
            } catch (\Throwable $e) {
                $this->warn("Payment {$payment->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Done. processed={$processed}, skipped={$skipped}, errors={$errors}".($dry ? ' (dry-run)' : ''));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
