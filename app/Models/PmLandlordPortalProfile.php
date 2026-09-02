<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmLandlordPortalProfile extends Model
{
    protected $table = 'pm_landlord_portal_profiles';

    protected $fillable = [
        'user_id',
        'kra_pin',
        'address_line',
        'bank_name',
        'bank_account',
        'mpesa_phone',
        'notify_email',
        'notify_sms',
        'last_acknowledged_statement_month',
        'alerts_last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'alerts_last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(User $user): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['notify_email' => true, 'notify_sms' => false]
        );
    }
}
