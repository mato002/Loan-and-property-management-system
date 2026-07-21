<?php

namespace App\Services\Loan;

use App\Models\LmMessageLog;

final class LoanPaymentReminderMessageLogResolver
{
    public function isPaymentReminder(LmMessageLog $log): bool
    {
        return (string) ($log->template_category ?? '') === 'payment_reminder';
    }
}
