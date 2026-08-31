<?php

namespace App\Services\Property;

class InvoiceDeliveryResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $emailedCount = 0,
        public int $smsedCount = 0,
        public array $errors = [],
    ) {}

    public function succeeded(): bool
    {
        return ($this->emailedCount + $this->smsedCount) > 0;
    }

    public function summaryMessage(string $invoiceNo): string
    {
        $parts = [];
        if ($this->emailedCount) {
            $parts[] = 'email sent';
        }
        if ($this->smsedCount) {
            $parts[] = 'SMS sent';
        }

        if ($parts === []) {
            return 'Invoice '.$invoiceNo.': delivery failed.';
        }

        $msg = 'Invoice '.$invoiceNo.': '.implode(' + ', $parts).'.';
        if ($this->errors !== []) {
            $msg .= ' Issues: '.implode(' ', $this->errors);
        }

        return $msg;
    }
}
