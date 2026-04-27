<?php

namespace App\Services;

use App\Models\LoanSystemSetting;
use App\Models\LoanTemporaryAccessRequest;
use App\Models\LoanUserDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanSecurityPolicyService
{
    public function ensureDefaults(): void
    {
        $defaults = [
            'loan_security_device_governance_enabled' => '0',
            'loan_security_role_login_windows_enabled' => '0',
            'loan_security_ip_restrictions_enabled' => '0',
            'loan_security_sensitive_routes_json' => json_encode([
                'loan.system.setup*',
                'loan.system.access_logs*',
                'loan.accounting*',
                'loan.financial*',
            ]),
            'loan_security_ip_allowlist_json' => json_encode([]),
            'loan_security_role_ip_overrides_json' => json_encode([]),
            'loan_security_role_login_windows_json' => json_encode([
                'admin' => ['enabled' => true, 'days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '00:00', 'end' => '23:59'],
                'manager' => ['enabled' => true, 'days' => [1, 2, 3, 4, 5], 'start' => '06:00', 'end' => '20:00'],
                'accountant' => ['enabled' => true, 'days' => [1, 2, 3, 4, 5], 'start' => '06:00', 'end' => '20:00'],
                'officer' => ['enabled' => true, 'days' => [1, 2, 3, 4, 5, 6], 'start' => '06:00', 'end' => '20:00'],
                'user' => ['enabled' => true, 'days' => [1, 2, 3, 4, 5, 6], 'start' => '06:00', 'end' => '20:00'],
                'applicant' => ['enabled' => true, 'days' => [1, 2, 3, 4, 5, 6], 'start' => '06:00', 'end' => '20:00'],
            ]),
        ];

        foreach ($defaults as $key => $value) {
            if (LoanSystemSetting::getValue($key) === null) {
                LoanSystemSetting::setValue($key, $value, $key, 'security');
            }
        }
    }

    public function deviceGovernanceEnabled(): bool
    {
        $this->ensureDefaults();
        return LoanSystemSetting::getValue('loan_security_device_governance_enabled', '0') === '1';
    }

    public function loginWindowEnabled(): bool
    {
        $this->ensureDefaults();
        return LoanSystemSetting::getValue('loan_security_role_login_windows_enabled', '0') === '1';
    }

    public function ipRestrictionsEnabled(): bool
    {
        $this->ensureDefaults();
        return LoanSystemSetting::getValue('loan_security_ip_restrictions_enabled', '0') === '1';
    }

    public function evaluateLoginPolicies(Request $request, User $user): ?string
    {
        if (! $user->isModuleApproved('loan')) {
            return null;
        }

        if ($this->loginWindowEnabled() && ! $this->isWithinRoleLoginWindow($user)) {
            if (! $this->hasMasterKey($user)) {
                return 'Login is outside the allowed time window for your role.';
            }
        }

        if ($this->deviceGovernanceEnabled()) {
            $deviceDecision = $this->enforceDeviceBinding($request, $user);
            if ($deviceDecision !== null) {
                return $deviceDecision;
            }
        }

        return null;
    }

    public function enforceDeviceBinding(Request $request, User $user): ?string
    {
        if (! Schema::hasTable('loan_user_devices')) {
            return null;
        }

        $fingerprint = $this->fingerprintFromRequest($request);
        $hash = hash('sha256', $fingerprint);
        $now = now();

        $existing = LoanUserDevice::query()
            ->where('user_id', $user->id)
            ->where('is_trusted', true)
            ->get();

        if ($existing->isEmpty()) {
            LoanUserDevice::query()->create([
                'user_id' => $user->id,
                'fingerprint_hash' => $hash,
                'fingerprint_label' => $this->fingerprintLabel($request),
                'is_trusted' => true,
                'bound_at' => $now,
                'last_seen_at' => $now,
                'last_seen_ip' => (string) $request->ip(),
                'last_seen_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'metadata' => [
                    'accept_language' => (string) $request->header('accept-language', ''),
                    'sec_ch_ua_platform' => (string) $request->header('sec-ch-ua-platform', ''),
                ],
            ]);
            return null;
        }

        $matched = $existing->firstWhere('fingerprint_hash', $hash);
        if ($matched) {
            $matched->update([
                'last_seen_at' => $now,
                'last_seen_ip' => (string) $request->ip(),
                'last_seen_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);
            return null;
        }

        if ($this->hasMasterKey($user)) {
            LoanUserDevice::query()->create([
                'user_id' => $user->id,
                'fingerprint_hash' => $hash,
                'fingerprint_label' => $this->fingerprintLabel($request).' (master-bound)',
                'is_trusted' => true,
                'bound_at' => $now,
                'last_seen_at' => $now,
                'last_seen_ip' => (string) $request->ip(),
                'last_seen_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);
            return null;
        }

        return 'This account is locked to a trusted device. Contact a director/super user to unbind your device.';
    }

    public function unbindUserDevices(User $targetUser): int
    {
        if (! Schema::hasTable('loan_user_devices')) {
            return 0;
        }

        return LoanUserDevice::query()
            ->where('user_id', $targetUser->id)
            ->delete();
    }

    public function isIpAllowedForRequest(Request $request, User $user): bool
    {
        if (! $this->ipRestrictionsEnabled()) {
            return true;
        }
        if (($user->is_super_admin ?? false) === true || $this->hasMasterKey($user)) {
            return true;
        }

        $routeName = (string) optional($request->route())->getName();
        if (! $this->isSensitiveRoute($routeName, $request->path())) {
            return true;
        }

        $global = $this->jsonList('loan_security_ip_allowlist_json');
        $roleOverrides = $this->jsonMap('loan_security_role_ip_overrides_json');
        $role = strtolower(trim($user->effectiveLoanRole()));
        $overrides = [];
        if (isset($roleOverrides[$role]) && is_array($roleOverrides[$role])) {
            foreach ($roleOverrides[$role] as $entry) {
                $overrides[] = trim((string) $entry);
            }
        }
        $allowList = array_values(array_unique(array_filter(array_merge($global, $overrides))));

        if ($allowList === []) {
            return true;
        }

        $ip = (string) $request->ip();
        foreach ($allowList as $range) {
            if ($this->ipMatches($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    public function isWithinRoleLoginWindow(User $user): bool
    {
        if (($user->is_super_admin ?? false) === true) {
            return true;
        }

        $windows = $this->jsonMap('loan_security_role_login_windows_json');
        $role = strtolower(trim($user->effectiveLoanRole()));
        $cfg = $windows[$role] ?? null;
        if (! is_array($cfg) || (($cfg['enabled'] ?? true) !== true)) {
            return true;
        }

        $days = is_array($cfg['days'] ?? null) ? $cfg['days'] : [1, 2, 3, 4, 5, 6, 7];
        $day = (int) now()->isoWeekday();
        if (! in_array($day, array_map('intval', $days), true)) {
            return false;
        }

        $start = trim((string) ($cfg['start'] ?? '00:00'));
        $end = trim((string) ($cfg['end'] ?? '23:59'));
        $nowTime = now()->format('H:i');

        return $nowTime >= $start && $nowTime <= $end;
    }

    public function canApproveTemporaryAccess(User $approver, LoanTemporaryAccessRequest $request): bool
    {
        if (($approver->is_super_admin ?? false) === true || $this->hasMasterKey($approver)) {
            return true;
        }

        $role = strtolower(trim($approver->effectiveLoanRole()));
        $amount = (float) ($request->amount_limit ?? 0);
        $perm = strtolower(trim((string) ($request->permission_key ?? '')));

        return match ($role) {
            'admin' => true,
            'manager' => $amount <= 2_000_000,
            'accountant' => $amount <= 500_000 && (str_contains($perm, 'payments.') || str_contains($perm, 'accounting.') || str_contains($perm, 'journals.')),
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    private function jsonList(string $settingKey): array
    {
        $raw = LoanSystemSetting::getValue($settingKey, '[]');
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        if (! is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonMap(string $settingKey): array
    {
        $raw = LoanSystemSetting::getValue($settingKey, '{}');
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    private function hasMasterKey(User $user): bool
    {
        return ($user->is_super_admin ?? false) === true || $user->hasLoanPermission('device_governance.master_key');
    }

    private function isSensitiveRoute(string $routeName, string $path): bool
    {
        $patterns = $this->jsonList('loan_security_sensitive_routes_json');
        foreach ($patterns as $pattern) {
            $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';
            if ($routeName !== '' && preg_match($regex, $routeName) === 1) {
                return true;
            }
            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }
        return false;
    }

    private function ipMatches(string $ip, string $range): bool
    {
        $range = trim($range);
        if ($range === '') {
            return false;
        }
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bitsRaw] = array_pad(explode('/', $range, 2), 2, null);
        $bits = (int) $bitsRaw;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long((string) $subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - $bits);
        $subnetLong &= $mask;

        return ($ipLong & $mask) === $subnetLong;
    }

    private function fingerprintFromRequest(Request $request): string
    {
        return implode('|', [
            (string) $request->header('user-agent', ''),
            (string) $request->header('accept-language', ''),
            (string) $request->header('sec-ch-ua', ''),
            (string) $request->header('sec-ch-ua-platform', ''),
            (string) $request->header('sec-ch-ua-mobile', ''),
            (string) $request->header('accept', ''),
        ]);
    }

    private function fingerprintLabel(Request $request): string
    {
        $platform = trim((string) $request->header('sec-ch-ua-platform', 'Unknown platform'), "\"' ");
        $agent = Str::limit((string) $request->header('user-agent', 'Unknown device'), 60, '');

        return $platform.' - '.$agent;
    }
}

