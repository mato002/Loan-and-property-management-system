<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\LandlordPortalAlertService;
use App\Services\Property\LandlordPortalNotifications;
use Illuminate\Console\Command;

class SendLandlordPortalAlertsCommand extends Command
{
    protected $signature = 'landlord:send-portal-alerts {--user= : Limit to one landlord user id}';

    protected $description = 'Email/SMS digest of landlord portal notifications for opted-in owners';

    public function handle(LandlordPortalAlertService $alerts): int
    {
        $query = User::query()->where('property_portal_role', 'landlord');
        if ($this->option('user')) {
            $query->where('id', (int) $this->option('user'));
        }

        $count = 0;
        foreach ($query->cursor() as $user) {
            $items = LandlordPortalNotifications::recent($user, 8);
            $prefs = $alerts->notificationPrefs($user);
            $filtered = array_values(array_filter($items, static function (array $n) use ($prefs): bool {
                $title = strtolower((string) ($n['title'] ?? ''));
                if (str_contains($title, 'rent') && ! ($prefs['notify_rent_collected'] ?? true)) {
                    return false;
                }
                if (str_contains($title, 'overdue') && ! ($prefs['notify_overdue'] ?? true)) {
                    return false;
                }
                if (str_contains($title, 'maintenance') && ! ($prefs['notify_maintenance'] ?? true)) {
                    return false;
                }
                if (str_contains($title, 'lease') && ! ($prefs['notify_lease_expiry'] ?? true)) {
                    return false;
                }

                return true;
            }));

            if ($filtered === []) {
                continue;
            }

            $result = $alerts->deliverDigest($user, $filtered);
            if (($result['email'] + $result['sms']) > 0) {
                $count++;
            }
        }

        $this->info('Delivered digests to '.$count.' landlord(s).');

        return self::SUCCESS;
    }
}
