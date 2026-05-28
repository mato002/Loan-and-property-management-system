<?php

namespace App\Exceptions\Property;

use Exception;

class UtilityPeriodClosedException extends Exception
{
    public function __construct(
        public readonly string $billingMonth,
        public readonly string $action,
        string $message = '',
    ) {
        parent::__construct($message !== ''
            ? $message
            : "Utility billing period {$billingMonth} is closed. Request a supervisor override to perform this action.");
    }
}
