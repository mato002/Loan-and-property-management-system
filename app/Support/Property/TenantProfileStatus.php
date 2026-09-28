<?php

namespace App\Support\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

final class TenantProfileStatus
{
    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const FORMER = 'former';

    public const DRAFT = 'draft';

    public const INACTIVE = 'inactive';

    /**
     * @return array{key: string, label: string, hint: string, tone: string}
     */
    public static function forTenant(PmTenant $tenant): array
    {
        if (isset($tenant->active_leases_count)
            || isset($tenant->expired_leases_count)
            || isset($tenant->terminated_leases_count)
            || isset($tenant->draft_leases_count)
        ) {
            return self::fromCounts(
                (int) ($tenant->active_leases_count ?? 0),
                (int) ($tenant->expired_leases_count ?? 0),
                (int) ($tenant->terminated_leases_count ?? 0),
                (int) ($tenant->draft_leases_count ?? 0),
            );
        }

        if ($tenant->relationLoaded('leases')) {
            return self::fromLeaseStatuses($tenant->leases->pluck('status')->all());
        }

        $counts = $tenant->leases()
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        return self::fromCounts(
            (int) ($counts[PmLease::STATUS_ACTIVE] ?? 0),
            (int) ($counts[PmLease::STATUS_EXPIRED] ?? 0),
            (int) ($counts[PmLease::STATUS_TERMINATED] ?? 0),
            (int) ($counts[PmLease::STATUS_DRAFT] ?? 0),
        );
    }

    /**
     * @param  list<string|null>  $statuses
     * @return array{key: string, label: string, hint: string, tone: string}
     */
    public static function fromLeaseStatuses(array $statuses): array
    {
        $active = 0;
        $expired = 0;
        $terminated = 0;
        $draft = 0;

        foreach ($statuses as $status) {
            $status = strtolower(trim((string) $status));
            if ($status === PmLease::STATUS_ACTIVE) {
                $active++;
            } elseif ($status === PmLease::STATUS_EXPIRED) {
                $expired++;
            } elseif ($status === PmLease::STATUS_TERMINATED) {
                $terminated++;
            } elseif ($status === PmLease::STATUS_DRAFT) {
                $draft++;
            }
        }

        return self::fromCounts($active, $expired, $terminated, $draft);
    }

    /**
     * @return array{key: string, label: string, hint: string, tone: string}
     */
    public static function fromCounts(int $active, int $expired, int $terminated, int $draft): array
    {
        if ($active > 0) {
            return self::definition(self::ACTIVE);
        }
        if ($expired > 0) {
            return self::definition(self::EXPIRED);
        }
        if ($terminated > 0) {
            return self::definition(self::FORMER);
        }
        if ($draft > 0) {
            return self::definition(self::DRAFT);
        }

        return self::definition(self::INACTIVE);
    }

    /**
     * @return array{key: string, label: string, hint: string, tone: string}
     */
    public static function definition(string $key): array
    {
        return match ($key) {
            self::ACTIVE => [
                'key' => self::ACTIVE,
                'label' => 'Active',
                'hint' => 'Occupying',
                'tone' => WorkspaceRowAlert::TONE_OCCUPIED,
            ],
            self::EXPIRED => [
                'key' => self::EXPIRED,
                'label' => 'Expired',
                'hint' => 'Lease lapsed',
                'tone' => WorkspaceRowAlert::TONE_ATTENTION,
            ],
            self::FORMER => [
                'key' => self::FORMER,
                'label' => 'Former',
                'hint' => 'Moved out',
                'tone' => WorkspaceRowAlert::TONE_VACANT,
            ],
            self::DRAFT => [
                'key' => self::DRAFT,
                'label' => 'Draft',
                'hint' => 'Lease not activated',
                'tone' => WorkspaceRowAlert::TONE_NOTICE,
            ],
            default => [
                'key' => self::INACTIVE,
                'label' => 'Inactive',
                'hint' => 'No lease',
                'tone' => WorkspaceRowAlert::TONE_VACANT,
            ],
        };
    }

    public static function badge(PmTenant $tenant): HtmlString
    {
        return self::badgeFrom(self::forTenant($tenant));
    }

    /**
     * @param  array{key?: string, label?: string, tone?: string}  $status
     */
    public static function badgeFrom(array $status): HtmlString
    {
        $tone = WorkspaceRowAlert::sanitizeTone((string) ($status['tone'] ?? ''));
        $modifier = match ($tone) {
            WorkspaceRowAlert::TONE_ATTENTION => 'attention',
            WorkspaceRowAlert::TONE_VACANT_LONG => 'vacant-long',
            WorkspaceRowAlert::TONE_VACANT => 'vacant',
            WorkspaceRowAlert::TONE_NOTICE => 'notice',
            WorkspaceRowAlert::TONE_OWNER_OCCUPIED => 'owner-occupied',
            WorkspaceRowAlert::TONE_OCCUPIED => 'occupied',
            default => 'vacant',
        };

        return new HtmlString(
            '<span class="property-status-pill property-status-pill--'.$modifier.'">'.e((string) ($status['label'] ?? 'Inactive')).'</span>'
        );
    }

    public static function pageTitle(PmTenant $tenant): HtmlString
    {
        $status = self::forTenant($tenant);

        return new HtmlString(
            '<span class="inline-flex flex-wrap items-center gap-2">'
            .'Tenant: '.e((string) $tenant->name).' '
            .self::badgeFrom($status)
            .'</span>'
        );
    }

    /**
     * @param  Builder<PmTenant>  $query
     */
    public static function addCounts(Builder $query): void
    {
        $query->withCount([
            'leases as active_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE),
            'leases as expired_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_EXPIRED),
            'leases as terminated_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_TERMINATED),
            'leases as draft_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_DRAFT),
        ]);
    }

    /**
     * @param  Builder<PmTenant>  $query
     */
    public static function applyFilter(Builder $query, string $status): void
    {
        $status = strtolower(trim($status));

        if ($status === self::ACTIVE) {
            $query->whereHas('leases', fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE));

            return;
        }

        if ($status === self::EXPIRED) {
            $query->whereDoesntHave('leases', fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE))
                ->whereHas('leases', fn ($q) => $q->where('status', PmLease::STATUS_EXPIRED));

            return;
        }

        if ($status === self::FORMER) {
            $query->whereDoesntHave('leases', fn ($q) => $q->whereIn('status', [PmLease::STATUS_ACTIVE, PmLease::STATUS_EXPIRED]))
                ->whereHas('leases', fn ($q) => $q->where('status', PmLease::STATUS_TERMINATED));

            return;
        }

        if ($status === self::DRAFT) {
            $query->whereDoesntHave('leases', fn ($q) => $q->whereIn('status', [
                PmLease::STATUS_ACTIVE,
                PmLease::STATUS_EXPIRED,
                PmLease::STATUS_TERMINATED,
            ]))->whereHas('leases', fn ($q) => $q->where('status', PmLease::STATUS_DRAFT));

            return;
        }

        if ($status === self::INACTIVE) {
            $query->whereDoesntHave('leases');
        }
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function filterOptions(): array
    {
        return [
            ['value' => self::ACTIVE, 'label' => 'Active'],
            ['value' => self::EXPIRED, 'label' => 'Expired'],
            ['value' => self::FORMER, 'label' => 'Former'],
            ['value' => self::DRAFT, 'label' => 'Draft'],
            ['value' => self::INACTIVE, 'label' => 'Inactive'],
        ];
    }
}
