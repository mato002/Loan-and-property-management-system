<?php

namespace App\Console\Commands;

use App\Models\LoanTemporaryAccessRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExpireLoanTemporaryAccessCommand extends Command
{
    protected $signature = 'loan:expire-temporary-access';
    protected $description = 'Expire approved temporary loan access that is past expiry';

    public function handle(): int
    {
        if (! Schema::hasTable('loan_temporary_access_requests')) {
            $this->warn('loan_temporary_access_requests table is missing.');
            return self::SUCCESS;
        }

        $affected = LoanTemporaryAccessRequest::query()
            ->where('status', LoanTemporaryAccessRequest::STATUS_APPROVED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => LoanTemporaryAccessRequest::STATUS_EXPIRED]);

        $this->info("Expired {$affected} temporary access request(s).");

        return self::SUCCESS;
    }
}

