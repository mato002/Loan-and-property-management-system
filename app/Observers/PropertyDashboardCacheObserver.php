<?php

namespace App\Observers;

use App\Services\Property\PropertyDashboardCache;

/**
 * Bumps dashboard cache version when portfolio or cash data changes.
 */
final class PropertyDashboardCacheObserver
{
    public function saved(): void
    {
        PropertyDashboardCache::forgetAll();
    }

    public function deleted(): void
    {
        PropertyDashboardCache::forgetAll();
    }

    public function restored(): void
    {
        PropertyDashboardCache::forgetAll();
    }
}
