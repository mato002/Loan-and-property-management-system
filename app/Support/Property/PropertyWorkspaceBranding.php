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
            'company_logo_url' => (string) (self::forPublicSite('company_logo_url', '') ?? ''),
            'site_favicon_url' => (string) (self::forPublicSite('site_favicon_url', '') ?? ''),
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
