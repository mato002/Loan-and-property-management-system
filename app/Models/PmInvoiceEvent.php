<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmInvoiceEvent extends Model
{
    protected $table = 'pm_invoice_events';

    public const EVENT_ISSUED = 'issued';
    public const EVENT_EDITED = 'edited';
    public const EVENT_SENT = 'sent';
    public const EVENT_REMINDED = 'reminded';
    public const EVENT_EMAILED = 'emailed';
    public const EVENT_SMS_SENT = 'sms_sent';
    public const EVENT_PARTIALLY_PAID = 'partially_paid';
    public const EVENT_PAID = 'paid';
    public const EVENT_OVERDUE = 'overdue';
    public const EVENT_CANCELLED = 'cancelled';
    public const EVENT_REOPENED = 'reopened';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_CREDIT_NOTE_ISSUED = 'credit_note_issued';
    public const EVENT_PENALTY_APPLIED = 'penalty_applied';

    protected $fillable = [
        'pm_invoice_id',
        'user_id',
        'event',
        'summary',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Convenience emitter. Always safe to call; never throws.
     */
    public static function record(
        int $invoiceId,
        string $event,
        ?int $userId = null,
        ?string $summary = null,
        ?array $payload = null,
    ): void {
        try {
            static::query()->create([
                'pm_invoice_id' => $invoiceId,
                'user_id' => $userId,
                'event' => $event,
                'summary' => $summary,
                'payload' => $payload,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the primary flow.
            report($e);
        }
    }
}
