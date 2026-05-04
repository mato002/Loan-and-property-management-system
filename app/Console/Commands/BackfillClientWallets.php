<?php

namespace App\Console\Commands;

use App\Models\LoanClient;
use App\Services\ClientWalletService;
use Illuminate\Console\Command;

class BackfillClientWallets extends Command
{
    protected $signature = 'loan:backfill-client-wallets {--dry-run : Show actions without writing}';

    protected $description = 'Create client_wallets rows for existing clients that are missing wallets.';

    public function handle(ClientWalletService $wallets): int
    {
        $dry = (bool) $this->option('dry-run');
        $clients = LoanClient::query()
            ->clients()
            ->whereDoesntHave('wallet')
            ->orderBy('id')
            ->get();

        if ($clients->isEmpty()) {
            $this->info('No clients need wallet rows.');

            return self::SUCCESS;
        }

        $this->info('Clients missing wallets: '.$clients->count());
        $created = 0;
        foreach ($clients as $client) {
            if ($dry) {
                $this->line("[dry-run] Would create wallet for client #{$client->id} {$client->client_number}");

                continue;
            }
            try {
                $wallets->ensureWallet($client, null);
                $created++;
            } catch (\Throwable $e) {
                $this->warn("Skipped client {$client->id}: {$e->getMessage()}");
            }
        }

        if ($dry) {
            $this->info('Dry run complete.');
        } else {
            $this->info("Created {$created} wallet(s).");
        }

        return self::SUCCESS;
    }
}
