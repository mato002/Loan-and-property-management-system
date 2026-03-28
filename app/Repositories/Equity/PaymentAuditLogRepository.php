<?php

namespace App\Repositories\Equity;

use App\Models\PaymentLog;

class PaymentAuditLogRepository
{
    public function api(string $status, array $payload, string $source = 'equity_api'): PaymentLog
    {
        return PaymentLog::query()->create([
            'source' => $source,
            'response' => $payload,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function decision(string $status, array $payload, string $source = 'equity_decision'): PaymentLog
    {
        return PaymentLog::query()->create([
            'source' => $source,
            'response' => $payload,
            'status' => $status,
            'created_at' => now(),
        ]);
    }
}

