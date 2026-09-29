<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PmLandlordPortalProfile extends Model
{
    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_CORPORATION = 'corporation';

    public const TYPE_ORGANIZATION = 'organization';

    public const TYPE_INSTITUTION = 'institution';

    public const TYPE_GOVERNMENT = 'government';

    /** @var array<string, string> */
    public const TYPES = [
        self::TYPE_INDIVIDUAL => 'Individual',
        self::TYPE_CORPORATION => 'Corporation',
        self::TYPE_ORGANIZATION => 'Organization',
        self::TYPE_INSTITUTION => 'Institution',
        self::TYPE_GOVERNMENT => 'Government',
    ];

    protected $table = 'pm_landlord_portal_profiles';

    protected $fillable = [
        'user_id',
        'legacy_landlord_code',
        'landlord_type',
        'id_number',
        'kra_pin',
        'address_line',
        'location',
        'bank_name',
        'bank_branch',
        'bank_account_name',
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

    public function documents(): HasMany
    {
        return $this->hasMany(PmLandlordDocument::class, 'user_id', 'user_id');
    }

    public function landlordTypeLabel(): string
    {
        $type = (string) ($this->landlord_type ?? '');

        return self::TYPES[$type] ?? ($type !== '' ? ucfirst($type) : '—');
    }

    public static function forUser(User $user): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['notify_email' => true, 'notify_sms' => false]
        );
    }
}
