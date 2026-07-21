<?php

namespace App\Services\Property;

use App\Models\PmLandlordPortalProfile;
use App\Models\User;
use App\Services\BulkSmsService;
use Illuminate\Support\Facades\Mail;

final class LandlordPortalAlertService
{
    public function __construct(
        private BulkSmsService $sms,
    ) {}

    /**
     * @param  list<array{title: string, body: string}>  $items
     */
    public function deliverDigest(User $user, array $items): array
    {
        if ($items === []) {
            return ['email' => 0, 'sms' => 0];
        }

        $profile = PmLandlordPortalProfile::forUser($user);
        $prefs = $this->notificationPrefs($user);
        $lines = array_map(static fn (array $n) => '• '.$n['title'].': '.$n['body'], $items);
        $body = "Portfolio update\n\n".implode("\n", $lines);

        $sent = ['email' => 0, 'sms' => 0];

        if ($profile->notify_email && ($prefs['notify_rent_collected'] ?? true)) {
            $email = trim((string) ($user->email ?? ''));
            if ($email !== '') {
                try {
                    Mail::raw($body, function ($m) use ($email) {
                        $m->to($email)->subject('Landlord portal update');
                    });
                    $sent['email'] = 1;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        if ($profile->notify_sms) {
            $phone = trim((string) ($profile->mpesa_phone ?? $user->phone ?? ''));
            if ($phone !== '') {
                try {
                    $this->sms->sendNow(mb_substr($body, 0, 480), [$phone], (int) $user->id, null, 'property');
                    $sent['sms'] = 1;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $profile->update(['alerts_last_sent_at' => now()]);

        return $sent;
    }

    /** @return array<string, bool> */
    public function notificationPrefs(User $user): array
    {
        $action = \App\Models\PmPortalAction::query()
            ->where('user_id', $user->id)
            ->where('portal_role', 'landlord')
            ->where('action_key', 'landlord_notification_preferences')
            ->latest('id')
            ->value('context');

        return is_array($action) ? $action : [
            'notify_rent_collected' => true,
            'notify_overdue' => true,
            'notify_maintenance' => true,
            'notify_lease_expiry' => true,
        ];
    }
}
