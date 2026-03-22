<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanSupportTicket extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_TECHNICAL = 'technical';

    public const CATEGORY_BILLING = 'billing';

    public const CATEGORY_ACCESS = 'access';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'ticket_number',
        'user_id',
        'subject',
        'body',
        'category',
        'priority',
        'status',
        'assigned_to_user_id',
        'resolution_notes',
    ];

    protected static function booted(): void
    {
        static::created(function (LoanSupportTicket $ticket): void {
            if ($ticket->ticket_number) {
                return;
            }
            $ticket->updateQuietly([
                'ticket_number' => 'TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(LoanSupportTicketReply::class, 'loan_support_ticket_id')->orderBy('created_at');
    }

    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        return (int) $this->user_id === (int) $user->id;
    }
}
