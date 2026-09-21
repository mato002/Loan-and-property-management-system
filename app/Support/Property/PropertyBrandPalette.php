<?php

namespace App\Support\Property;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

final class PropertyBrandPalette
{
    public const PLATFORM = 'platform';

    public const PASSION_HOMES = 'passion-homes';

    /** @var list<string> */
    public const OPTIONS = [
        self::PLATFORM,
        self::PASSION_HOMES,
    ];

    /**
     * Named palettes. Switch `brand_palette` in branding settings and public + portal chrome follow.
     *
     * @return array<string, array{
     *     label: string,
     *     hint: string,
     *     primary: string,
     *     primary_hover: string,
     *     primary_dark: string,
     *     primary_soft: string,
     *     cta: string,
     *     cta_hover: string,
     *     gold: string,
     *     navy: string,
     *     navy_hover: string,
     *     navy_border: string,
     *     highlight: string,
     *     on_primary: string
     * }>
     */
    public static function all(): array
    {
        return [
            self::PLATFORM => [
                'label' => 'Platform teal',
                'hint' => 'Current system green used across the product.',
                'primary' => '#059669',
                'primary_hover' => '#047857',
                'primary_dark' => '#065f46',
                'primary_soft' => '#ecfdf5',
                'cta' => '#059669',
                'cta_hover' => '#047857',
                'gold' => '#f59e0b',
                'navy' => '#2f4f4f',
                'navy_hover' => '#406866',
                'navy_border' => '#264040',
                'highlight' => '#6ee7b7',
                'on_primary' => '#ffffff',
            ],
            self::PASSION_HOMES => [
                'label' => 'Passion Homes logo',
                'hint' => 'Two-color mark: electric blue roof/H, red P and house.',
                'primary' => '#0000F0',
                'primary_hover' => '#0000C8',
                'primary_dark' => '#000099',
                'primary_soft' => '#EEEEFF',
                'cta' => '#EA1B1B',
                'cta_hover' => '#C41616',
                'gold' => '#EA1B1B',
                'navy' => '#000066',
                'navy_hover' => '#000080',
                'navy_border' => '#00004D',
                'highlight' => '#9D9DFF',
                'on_primary' => '#ffffff',
            ],
        ];
    }

    public static function normalize(?string $key): string
    {
        $key = strtolower(trim((string) $key));

        return in_array($key, self::OPTIONS, true) ? $key : self::PLATFORM;
    }

    /**
     * @return array<string, string>
     */
    public static function tokens(?string $key = null): array
    {
        $palettes = self::all();
        $normalized = self::normalize($key);

        return $palettes[$normalized];
    }

    public static function color(?string $key, string $token = 'primary'): string
    {
        $tokens = self::tokens($key);

        return (string) ($tokens[$token] ?? $tokens['primary']);
    }

    public static function resolve(string $surface = 'portal'): string
    {
        $surface = strtolower(trim($surface));

        $raw = match ($surface) {
            'public' => PropertyWorkspaceBranding::forPublicSite('brand_palette', self::PLATFORM),
            'guest' => PropertyWorkspaceBranding::forGuestPage('brand_palette', self::PLATFORM),
            default => PropertyWorkspaceBranding::get('brand_palette', self::PLATFORM),
        };

        return self::normalize($raw);
    }

    public static function cssDeclarations(?string $key = null): string
    {
        $t = self::tokens($key);

        return implode('; ', [
            '--brand-primary: '.$t['primary'],
            '--brand-primary-hover: '.$t['primary_hover'],
            '--brand-primary-dark: '.$t['primary_dark'],
            '--brand-primary-soft: '.$t['primary_soft'],
            '--brand-cta: '.$t['cta'],
            '--brand-cta-hover: '.$t['cta_hover'],
            '--brand-gold: '.$t['gold'],
            '--brand-navy: '.$t['navy'],
            '--brand-navy-hover: '.$t['navy_hover'],
            '--brand-navy-border: '.$t['navy_border'],
            '--brand-highlight: '.$t['highlight'],
            '--brand-on-primary: '.$t['on_primary'],
        ]);
    }

    public static function htmlRootAttributes(string $surface = 'portal'): HtmlString
    {
        $key = self::resolve($surface);

        return new HtmlString(
            'data-brand-palette="'.e($key).'" style="'.self::cssDeclarations($key).'"'
        );
    }

    public static function persist(?string $key, ?int $agentUserId = null, ?User $user = null): string
    {
        $normalized = self::normalize($key);

        if ($agentUserId !== null && $agentUserId > 0) {
            PropertyWorkspaceBranding::set('brand_palette', $normalized, $agentUserId);
            self::syncDocumentColour($normalized, $agentUserId);
        } else {
            PropertyWorkspaceBranding::setForSettings('brand_palette', $normalized, $user ?? Auth::user());
            self::syncDocumentColour($normalized, PropertyWorkspaceBranding::settingsEditorAgentUserId($user));
        }

        return $normalized;
    }

    private static function syncDocumentColour(string $paletteKey, ?int $agentUserId): void
    {
        $colour = self::color($paletteKey, 'primary');
        $raw = $agentUserId
            ? (string) (PropertyWorkspaceBranding::getForAgent('branding', $agentUserId, '') ?? '')
            : (string) (PropertyWorkspaceBranding::getForSettings('branding', '') ?? '');

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }
        $decoded['colour'] = $colour;
        $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{"colour":"'.$colour.'"}';

        if ($agentUserId) {
            PropertyWorkspaceBranding::set('branding', $json, $agentUserId);

            return;
        }

        PropertyWorkspaceBranding::setForSettings('branding', $json);
    }
}
