<?php

namespace App\Support\Property;

use App\Models\PropertyPortalSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class PropertyWorkspaceBranding
{
    /** @var list<string> */
    public const KEYS = [
        'company_name',
        'company_logo_url',
        'site_favicon_url',
        'public_website_domain',
        'contact_email_primary',
        'contact_email_support',
        'contact_phone',
        'contact_whatsapp',
        'contact_address',
        'contact_reg_no',
        'contact_map_embed_url',
        'portal_color_theme',
        'branding',
    ];

    public static function isBrandingKey(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! self::isBrandingKey($key)) {
            return PropertyPortalSetting::getGlobalValue($key, $default);
        }

        $agentUserId = self::resolveViewerAgentUserId();
        if ($agentUserId !== null) {
            $scoped = self::readScopedValue($key, $agentUserId);
            if ($scoped !== null && $scoped !== '') {
                return $scoped;
            }
        }

        if ($agentUserId === null) {
            return $default ?? config('app.name', 'Property Platform');
        }

        return $default ?? config('app.name', 'Property ERP');
    }

    public static function set(string $key, ?string $value, ?int $agentUserId = null): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            PropertyPortalSetting::setGlobalValue($key, $value);

            return;
        }

        $agentUserId = $agentUserId ?? self::storeAgentUserId();

        PropertyPortalSetting::query()->updateOrCreate(
            [
                'agent_user_id' => $agentUserId,
                'key' => $key,
            ],
            ['value' => $value]
        );
    }

    /**
     * Agent user id whose branding the current viewer should see.
     * Null = platform operator (super admin) — not an agent workspace.
     */
    public static function resolveViewerAgentUserId(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        if (($user->is_super_admin ?? false) === true) {
            return null;
        }

        $role = strtolower(trim((string) ($user->property_portal_role ?? '')));

        if ($role === 'agent') {
            return (int) $user->id;
        }

        if ($role === 'tenant') {
            return self::tenantAgentUserId($user);
        }

        if ($role === 'landlord') {
            return self::landlordAgentUserId($user);
        }

        return null;
    }

    /** User id to stamp when saving branding from Settings. */
    public static function storeAgentUserId(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        if (strtolower(trim((string) ($user->property_portal_role ?? ''))) === 'agent') {
            return (int) $user->id;
        }

        return null;
    }

    /**
     * Agent workspace whose branding the Settings → Branding form should load and save.
     */
    public static function settingsEditorAgentUserId(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        $agentUserId = self::storeAgentUserId($user);
        if ($agentUserId !== null) {
            return $agentUserId;
        }

        if (($user->is_super_admin ?? false) === true) {
            return self::loginBrandingAgentUserId();
        }

        return null;
    }

    public static function getForSettings(string $key, ?string $default = ''): ?string
    {
        if (! self::isBrandingKey($key)) {
            return PropertyPortalSetting::getGlobalValue($key, $default);
        }

        $agentUserId = self::settingsEditorAgentUserId();
        if ($agentUserId !== null) {
            $scoped = self::readScopedValue($key, $agentUserId);
            if ($scoped !== null && $scoped !== '') {
                return $scoped;
            }
        }

        $global = PropertyPortalSetting::getGlobalValue($key);
        if ($global !== null && $global !== '') {
            return $global;
        }

        return $default;
    }

    /**
     * Read branding for a specific agent workspace (Super Admin editing without impersonation).
     */
    public static function getForAgent(string $key, int $agentUserId, ?string $default = ''): ?string
    {
        if ($agentUserId <= 0) {
            return $default;
        }

        if (! self::isBrandingKey($key)) {
            return PropertyPortalSetting::getGlobalValue($key, $default);
        }

        $scoped = self::readScopedValue($key, $agentUserId);
        if ($scoped !== null && $scoped !== '') {
            return $scoped;
        }

        return $default;
    }

    public static function setForSettings(string $key, ?string $value, ?User $user = null): void
    {
        if (! self::isBrandingKey($key)) {
            PropertyPortalSetting::setGlobalValue($key, $value);

            return;
        }

        $user = $user ?? Auth::user();
        $agentUserId = self::settingsEditorAgentUserId($user);

        if ($agentUserId !== null) {
            self::set($key, $value, $agentUserId);

            return;
        }

        if ($user instanceof User && ($user->is_super_admin ?? false) === true) {
            PropertyPortalSetting::setGlobalValue($key, $value);

            return;
        }

        self::set($key, $value);
    }

    public static function canEditSettingsBranding(?User $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if (self::settingsEditorAgentUserId($user) !== null) {
            return true;
        }

        return ($user->is_super_admin ?? false) === true;
    }

    public static function cacheScopeKey(): string
    {
        return (string) (self::resolveViewerAgentUserId() ?? 'platform');
    }

    /**
     * Branding for unauthenticated pages (staff login, password reset, etc.).
     * When a login agent is configured (env or sole agent), that workspace wins over
     * legacy global rows so stale platform names do not override tenant branding.
     */
    public static function forGuestPage(string $key, ?string $default = null): ?string
    {
        if (! self::isBrandingKey($key)) {
            return PropertyPortalSetting::getGlobalValue($key, $default);
        }

        $loginAgentUserId = self::loginBrandingAgentUserId();
        if ($loginAgentUserId !== null) {
            $scoped = self::readScopedValue($key, $loginAgentUserId);
            if ($scoped !== null && $scoped !== '') {
                return $scoped;
            }
        }

        $global = PropertyPortalSetting::getGlobalValue($key);
        if ($global !== null && $global !== '') {
            return $global;
        }

        if ($key === 'company_name') {
            return $default ?? config('app.name', 'Property Platform');
        }

        return $default;
    }

    /**
     * Branding for the public marketing site (unauthenticated).
     * Resolves tenant from request host → agent-scoped settings → global → APP_NAME.
     */
    public static function forPublicSite(string $key, ?string $default = null): ?string
    {
        if (! self::isBrandingKey($key)) {
            return PropertyPortalSetting::getGlobalValue($key, $default);
        }

        if ($key === 'public_website_domain') {
            return self::readPublicWebsiteDomainForAgent(self::resolvePublicSiteAgentUserId());
        }

        $agentUserId = self::resolvePublicSiteAgentUserId();
        if ($agentUserId !== null) {
            $scoped = self::readScopedValue($key, $agentUserId);
            if ($scoped !== null && $scoped !== '') {
                return $scoped;
            }
        }

        $global = PropertyPortalSetting::getGlobalValue($key);
        if ($global !== null && $global !== '') {
            return $global;
        }

        if ($key === 'company_name') {
            return $default ?? config('app.name', 'Property Platform');
        }

        return $default;
    }

    /**
     * @return array{
     *     company_name: string,
     *     company_logo_url: string,
     *     site_favicon_url: string,
     *     contact_email_primary: string,
     *     contact_email_support: string,
     *     contact_phone: string,
     *     contact_whatsapp: string,
     *     contact_address: string,
     *     contact_reg_no: string,
     *     contact_map_embed_url: string,
     *     public_site_agent_user_id: int|null
     * }
     */
    public static function publicSiteSnapshot(): array
    {
        $agentUserId = self::resolvePublicSiteAgentUserId();

        return [
            'company_name' => (string) (self::forPublicSite('company_name', config('app.name', 'Property Platform')) ?? config('app.name', 'Property Platform')),
            'company_logo_url' => self::resolveAssetUrl((string) (self::forPublicSite('company_logo_url', '') ?? '')),
            'site_favicon_url' => self::resolveAssetUrl((string) (self::forPublicSite('site_favicon_url', '') ?? '')),
            'contact_email_primary' => (string) (self::forPublicSite('contact_email_primary', '') ?? ''),
            'contact_email_support' => (string) (self::forPublicSite('contact_email_support', '') ?? ''),
            'contact_phone' => (string) (self::forPublicSite('contact_phone', '') ?? ''),
            'contact_whatsapp' => (string) (self::forPublicSite('contact_whatsapp', '') ?? ''),
            'contact_address' => (string) (self::forPublicSite('contact_address', '') ?? ''),
            'contact_reg_no' => (string) (self::forPublicSite('contact_reg_no', '') ?? ''),
            'contact_map_embed_url' => (string) (self::forPublicSite('contact_map_embed_url', '') ?? ''),
            'public_site_agent_user_id' => $agentUserId,
        ];
    }

    /**
     * WhatsApp wa.me digits. Falls back to the public phone when WhatsApp is empty.
     */
    public static function whatsappDigitsForWeb(?string $whatsapp = null, ?string $phone = null): string
    {
        $raw = trim((string) $whatsapp);
        if ($raw === '') {
            $raw = trim((string) $phone);
        }

        return self::normalizeKenyanMobileDigits($raw);
    }

    public static function normalizeKenyanMobileDigits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '254') && strlen($digits) >= 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            return '254'.$digits;
        }

        return $digits;
    }

    /**
     * Branding for receipts, prints, PDF exports, and browser print letterheads.
     * Prefers the viewing agent's workspace, then Settings branding / login tenant, then global.
     *
     * @param  int|null  $agentUserId  Optional property/agent owner to brand the document for.
     * @return array{
     *     company_name: string,
     *     company_logo_url: string,
     *     logo_url: string,
     *     logo_src: string,
     *     contact_email_primary: string,
     *     contact_phone: string,
     *     contact_address: string,
     *     contact_reg_no: string,
     *     colour: string,
     *     address: string,
     *     phone: string,
     *     email: string,
     *     contact_line: string
     * }
     */
    public static function documentSnapshot(?int $agentUserId = null): array
    {
        $companyName = self::documentValue('company_name', $agentUserId, config('app.name', 'Property Manager'));
        $logoRaw = self::documentValue('company_logo_url', $agentUserId, '');
        $logoUrl = self::resolveAssetUrl($logoRaw);
        $logoSrc = self::resolveLocalFileSrc($logoRaw) ?: $logoUrl;
        $phone = self::documentValue('contact_phone', $agentUserId, '');
        $email = self::documentValue('contact_email_primary', $agentUserId, '');
        $address = self::documentValue('contact_address', $agentUserId, '');
        $regNo = self::documentValue('contact_reg_no', $agentUserId, '');
        $colour = '#0f766e';

        $brandingRaw = self::documentValue('branding', $agentUserId, '');
        if ($brandingRaw !== '') {
            $decoded = json_decode($brandingRaw, true);
            if (is_array($decoded)) {
                if (! empty($decoded['colour'])) {
                    $colour = (string) $decoded['colour'];
                }
                if ($companyName === '' && ! empty($decoded['company_name'])) {
                    $companyName = (string) $decoded['company_name'];
                }
                if ($logoUrl === '' && ! empty($decoded['logo_url'])) {
                    $logoUrl = self::resolveAssetUrl((string) $decoded['logo_url']);
                    $logoSrc = self::resolveLocalFileSrc((string) $decoded['logo_url']) ?: $logoUrl;
                }
                if ($address === '' && ! empty($decoded['address'])) {
                    $address = (string) $decoded['address'];
                }
                if ($phone === '' && ! empty($decoded['phone'])) {
                    $phone = (string) $decoded['phone'];
                }
                if ($email === '' && ! empty($decoded['email'])) {
                    $email = (string) $decoded['email'];
                }
            }
        }

        $companyName = trim($companyName) !== '' ? trim($companyName) : (string) config('app.name', 'Property Manager');
        if (strtolower($companyName) === 'laravel') {
            $companyName = 'Property Manager';
        }

        $contactLine = collect([$phone, $email, $address, $regNo !== '' ? 'Reg: '.$regNo : ''])
            ->filter(static fn ($part) => trim((string) $part) !== '')
            ->implode(' · ');

        return [
            'company_name' => $companyName,
            'company_logo_url' => $logoUrl,
            'logo_url' => $logoUrl,
            'logo_src' => $logoSrc,
            'contact_email_primary' => $email,
            'contact_phone' => $phone,
            'contact_address' => $address,
            'contact_reg_no' => $regNo,
            'colour' => $colour,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'contact_line' => $contactLine,
        ];
    }

    public static function resolveAssetUrl(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        // Branding uploads: serve via app route so logos work without public/storage symlink.
        $brandingPath = self::extractBrandingDiskPath($raw);
        if ($brandingPath !== null) {
            return url('/media/branding/'.$brandingPath);
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://') || str_starts_with($raw, 'data:')) {
            return $raw;
        }

        if (str_starts_with($raw, '/')) {
            return url($raw);
        }

        if (str_starts_with($raw, 'storage/')) {
            return url('/'.$raw);
        }

        return url('/storage/'.ltrim($raw, '/'));
    }

    /**
     * Normalize stored branding logo/favicon values to a public-disk relative path
     * under property/branding/… (without that prefix), or null if not a branding asset.
     */
    public static function extractBrandingDiskPath(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_starts_with($raw, 'data:')) {
            return null;
        }

        $path = $raw;
        if (preg_match('#^https?://[^/]+(/.*)$#i', $raw, $matches) === 1) {
            $path = (string) $matches[1];
        }

        $path = str_replace('\\', '/', $path);
        $path = '/'.ltrim($path, '/');

        foreach (['/storage/property/branding/', '/media/branding/', '/property/branding/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $relative = ltrim(substr($path, strlen($prefix)), '/');

                return $relative !== '' ? $relative : null;
            }
        }

        if (str_starts_with(ltrim($path, '/'), 'property/branding/')) {
            $relative = substr(ltrim($path, '/'), strlen('property/branding/'));

            return $relative !== '' ? $relative : null;
        }

        return null;
    }

    /**
     * Prefer a local filesystem path for DomPDF when the asset lives under public/storage.
     */
    public static function resolveLocalFileSrc(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://') || str_starts_with($raw, 'data:')) {
            return '';
        }

        $relative = $raw;
        if (str_starts_with($relative, '/storage/')) {
            $relative = substr($relative, strlen('/storage/'));
        } elseif (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        $path = public_path('storage/'.ltrim($relative, '/'));
        if (is_file($path)) {
            return $path;
        }

        return '';
    }

    private static function documentValue(string $key, ?int $preferredAgentUserId, ?string $default = null): string
    {
        $candidates = array_values(array_unique(array_filter([
            $preferredAgentUserId,
            self::resolveViewerAgentUserId(),
            self::settingsEditorAgentUserId(),
            self::loginBrandingAgentUserId(),
        ], static fn ($id) => $id !== null && (int) $id > 0)));

        foreach ($candidates as $agentUserId) {
            $scoped = self::readScopedValue($key, (int) $agentUserId);
            if ($scoped !== null && trim($scoped) !== '') {
                return trim($scoped);
            }
        }

        $global = PropertyPortalSetting::getGlobalValue($key);
        if ($global !== null && trim((string) $global) !== '') {
            return trim((string) $global);
        }

        return trim((string) ($default ?? ''));
    }

    public static function resolvePublicSiteAgentUserId(?string $host = null): ?int
    {
        static $cache = [];

        $normalizedHost = self::normalizePublicHost($host ?? (string) request()->getHost());
        if (array_key_exists($normalizedHost, $cache)) {
            return $cache[$normalizedHost];
        }

        $map = config('property.public_site_domains', []);
        if ($normalizedHost !== '' && isset($map[$normalizedHost])) {
            return $cache[$normalizedHost] = (int) $map[$normalizedHost];
        }

        if ($normalizedHost !== '' && Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $matches = PropertyPortalSetting::query()
                ->where('key', 'public_website_domain')
                ->whereNotNull('agent_user_id')
                ->where('value', '!=', '')
                ->get(['agent_user_id', 'value']);

            foreach ($matches as $row) {
                if (self::normalizePublicHost((string) $row->value) === $normalizedHost) {
                    return $cache[$normalizedHost] = (int) $row->agent_user_id;
                }
            }
        }

        $configured = config('property.public_site_agent_user_id');
        if ($configured !== null && $configured !== '') {
            return $cache[$normalizedHost] = (int) $configured;
        }

        $loginAgent = self::loginBrandingAgentUserId();
        if ($loginAgent !== null) {
            return $cache[$normalizedHost] = $loginAgent;
        }

        return $cache[$normalizedHost] = null;
    }

    public static function normalizePublicHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private static function readPublicWebsiteDomainForAgent(?int $agentUserId): ?string
    {
        if ($agentUserId === null) {
            return null;
        }

        return self::readScopedValue('public_website_domain', $agentUserId);
    }

    private static function loginBrandingAgentUserId(): ?int
    {
        $configured = config('property.login_branding_agent_user_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            return null;
        }

        $agentIds = PropertyPortalSetting::query()
            ->where('key', 'company_name')
            ->whereNotNull('agent_user_id')
            ->where('value', '!=', '')
            ->pluck('agent_user_id')
            ->unique()
            ->values();

        if ($agentIds->count() === 1) {
            return (int) $agentIds->first();
        }

        return null;
    }

    private static function readScopedValue(string $key, int $agentUserId): ?string
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            return PropertyPortalSetting::getGlobalValue($key);
        }

        return PropertyPortalSetting::query()
            ->where('key', $key)
            ->where('agent_user_id', $agentUserId)
            ->value('value');
    }

    private static function tenantAgentUserId(User $user): ?int
    {
        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            return null;
        }

        $tenantId = $user->pm_tenant_id ?? null;
        if (! $tenantId) {
            return null;
        }

        $agentId = \App\Models\PmTenant::query()
            ->whereKey((int) $tenantId)
            ->value('agent_user_id');

        return $agentId ? (int) $agentId : null;
    }

    private static function landlordAgentUserId(User $user): ?int
    {
        if (! Schema::hasColumn('users', 'agent_user_id')) {
            return null;
        }

        $agentId = $user->agent_user_id ?? null;

        return $agentId ? (int) $agentId : null;
    }
}
