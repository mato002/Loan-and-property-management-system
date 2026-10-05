<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmBankCollectionAuditLog extends Model
{
    protected $table = 'pm_bank_collection_audit_logs';

    protected $fillable = [
        'provider',
        'event',
        'external_transaction_reference',
        'tenant_account_number',
        'tenant_id',
        'payment_id',
        'outcome',
        'message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
