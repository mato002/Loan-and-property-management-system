<?php

namespace App\Support\Property;

use App\Models\PropertyUnit;
use Illuminate\Support\HtmlString;

final class WorkspaceRowAlert
{
    public const TONE_OCCUPIED = 'occupied';

    public const TONE_VACANT = 'vacant';

    public const TONE_VACANT_LONG = 'vacant-long';

    public const TONE_OWNER_OCCUPIED = 'owner-occupied';

    public const TONE_NOTICE = 'notice';

    public const TONE_ATTENTION = 'attention';

    public const VACANT_LONG_DAYS = 90;

    /**
     * Exact cell/line tokens → tone. Keep in sync with resources/js/property-row-alerts.js.
     *
     * @return array<string, string>
     */
    public static function tokenMap(): array
    {
        return [
            'no active lease' => self::TONE_ATTENTION,
            'overdue' => self::TONE_ATTENTION,
            'unpaid' => self::TONE_ATTENTION,
            'failed' => self::TONE_ATTENTION,
            'rejected' => self::TONE_ATTENTION,
            'expired' => self::TONE_ATTENTION,
            'emergency' => self::TONE_ATTENTION,
            'urgent' => self::TONE_ATTENTION,
            'urgent renewal call' => self::TONE_ATTENTION,
            'bounced' => self::TONE_ATTENTION,
            'defaulted' => self::TONE_ATTENTION,
            'watchlist' => self::TONE_ATTENTION,
            'npl' => self::TONE_ATTENTION,
            'written off' => self::TONE_ATTENTION,
            'high' => self::TONE_ATTENTION,
            'severe' => self::TONE_ATTENTION,
            'critical' => self::TONE_ATTENTION,
            'blocked' => self::TONE_ATTENTION,
            'suspended' => self::TONE_ATTENTION,
            'declined' => self::TONE_ATTENTION,
            'past due' => self::TONE_ATTENTION,
            'error' => self::TONE_ATTENTION,
            'uninvoiced' => self::TONE_ATTENTION,

            'long vacant' => self::TONE_VACANT_LONG,
            '90+ days' => self::TONE_VACANT_LONG,
            '90 plus days' => self::TONE_VACANT_LONG,
            '90+ days vacant' => self::TONE_VACANT_LONG,

            'vacant' => self::TONE_VACANT,
            'vacancy' => self::TONE_VACANT,

            'occupied' => self::TONE_OCCUPIED,

            'owner occupied' => self::TONE_OWNER_OCCUPIED,
            'owner_occupied' => self::TONE_OWNER_OCCUPIED,

            'notice' => self::TONE_NOTICE,
            'pending' => self::TONE_NOTICE,
            'draft' => self::TONE_NOTICE,
            'in progress' => self::TONE_NOTICE,
            'partial' => self::TONE_NOTICE,
            'sent' => self::TONE_NOTICE,
            'medium' => self::TONE_NOTICE,
            'due soon' => self::TONE_NOTICE,
            'send renewal offer' => self::TONE_NOTICE,
            'not sent to tenant' => self::TONE_NOTICE,
            'pending disbursement' => self::TONE_NOTICE,
            'expiring' => self::TONE_NOTICE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedTones(): array
    {
        return [
            self::TONE_OCCUPIED,
            self::TONE_OWNER_OCCUPIED,
            self::TONE_VACANT,
            self::TONE_VACANT_LONG,
            self::TONE_NOTICE,
            self::TONE_ATTENTION,
        ];
    }

    public static function sanitizeTone(?string $tone): string
    {
        $tone = trim((string) $tone);

        return in_array($tone, self::allowedTones(), true) ? $tone : '';
    }

    public static function trClass(?string $tone): string
    {
        $tone = self::sanitizeTone($tone);

        return $tone !== '' ? 'property-row-alert-'.$tone : '';
    }

    public static function fillColor(?string $tone): string
    {
        return match (self::sanitizeTone($tone)) {
            self::TONE_OCCUPIED => '#bbf7d0',
            self::TONE_OWNER_OCCUPIED => '#fdba74',
            self::TONE_VACANT => '#facc15',
            self::TONE_VACANT_LONG => '#fb923c',
            self::TONE_NOTICE => '#38bdf8',
            self::TONE_ATTENTION => '#fb7185',
            default => '',
        };
    }

    public static function cellStyle(?string $tone): string
    {
        $color = self::fillColor($tone);

        return $color === '' ? '' : 'background-color: '.$color.' !important';
    }

    /**
     * @param  list<mixed>  $row
     */
    public static function resolve(array $row, ?string $explicit = null): string
    {
        $explicit = self::sanitizeTone($explicit);
        if ($explicit !== '') {
            return $explicit;
        }

        return self::inferFromRow($row);
    }

    /**
     * @param  list<mixed>  $row
     */
    public static function inferFromRow(array $row): string
    {
        $rank = [
            self::TONE_ATTENTION => 5,
            self::TONE_VACANT_LONG => 4,
            self::TONE_VACANT => 3,
            self::TONE_NOTICE => 2,
            self::TONE_OCCUPIED => 1,
            self::TONE_OWNER_OCCUPIED => 1,
        ];
        $best = '';
        $bestRank = 0;

        foreach ($row as $cell) {
            $html = (string) $cell;
            if (preg_match('/property-status-pill--([a-z-]+)/', $html, $match)) {
                $fromPill = self::sanitizeTone((string) ($match[1] ?? ''));
                if ($fromPill !== '' && ($rank[$fromPill] ?? 0) > $bestRank) {
                    $best = $fromPill;
                    $bestRank = $rank[$fromPill];
                }
            }

            foreach (self::cellLines($cell) as $line) {
                $tone = self::tokenMap()[$line] ?? '';
                if ($tone !== '' && ($rank[$tone] ?? 0) > $bestRank) {
                    $best = $tone;
                    $bestRank = $rank[$tone];
                }
            }
        }

        return $best;
    }

    public static function forUnit(PropertyUnit $unit, bool $hasActiveLease): string
    {
        $status = (string) $unit->status;

        if ($status === PropertyUnit::STATUS_OCCUPIED && ! $hasActiveLease) {
            return self::TONE_ATTENTION;
        }

        if ($status === PropertyUnit::STATUS_VACANT && $hasActiveLease) {
            return self::TONE_ATTENTION;
        }

        if ($status === PropertyUnit::STATUS_VACANT) {
            $days = $unit->vacant_since ? (int) $unit->vacant_since->diffInDays(now()) : 0;

            return $days >= self::VACANT_LONG_DAYS ? self::TONE_VACANT_LONG : self::TONE_VACANT;
        }

        if ($status === PropertyUnit::STATUS_NOTICE) {
            return self::TONE_NOTICE;
        }

        if ($status === PropertyUnit::STATUS_OWNER_OCCUPIED) {
            return self::TONE_OWNER_OCCUPIED;
        }

        if ($status === PropertyUnit::STATUS_OCCUPIED) {
            return self::TONE_OCCUPIED;
        }

        return '';
    }

    public static function forSnapshot(string $status, bool $hasTenant, float $arrears = 0, mixed $vacantSince = null): string
    {
        $status = mb_strtolower(trim($status));

        if ($status === PropertyUnit::STATUS_OCCUPIED && ! $hasTenant) {
            return self::TONE_ATTENTION;
        }

        if ($arrears > 0.009 && $status === PropertyUnit::STATUS_OCCUPIED) {
            return self::TONE_ATTENTION;
        }

        if ($status === PropertyUnit::STATUS_VACANT) {
            $days = 0;
            if ($vacantSince) {
                try {
                    $days = (int) \Illuminate\Support\Carbon::parse((string) $vacantSince)->diffInDays(now());
                } catch (\Throwable) {
                    $days = 0;
                }
            }

            return $days >= self::VACANT_LONG_DAYS ? self::TONE_VACANT_LONG : self::TONE_VACANT;
        }

        if ($status === PropertyUnit::STATUS_NOTICE) {
            return self::TONE_NOTICE;
        }

        if ($status === PropertyUnit::STATUS_OWNER_OCCUPIED) {
            return self::TONE_OWNER_OCCUPIED;
        }

        if ($status === PropertyUnit::STATUS_OCCUPIED && $hasTenant) {
            return self::TONE_OCCUPIED;
        }

        return self::inferFromRow([$status]);
    }

    /**
     * @return list<string>
     */
    private static function cellLines(mixed $cell): array
    {
        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", (string) $cell) ?? '';
        $html = preg_replace('/<\/(p|div|li|h[1-6]|tr|span|td)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(['_', '-'], ' ', $text);
        $parts = preg_split('/\R+/', $text) ?: [];
        $lines = [];

        foreach ($parts as $part) {
            $line = mb_strtolower(trim(preg_replace('/\s+/', ' ', $part) ?? $part));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
