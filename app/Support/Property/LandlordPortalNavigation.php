<?php

namespace App\Support\Property;

final class LandlordPortalNavigation
{
    /**
     * Primary sidebar destinations (7 items).
     *
     * @return array<string, array{route: string, active: string|list<string>, icon: string}>
     */
    public static function sidebarItems(): array
    {
        return [
            'Portfolio overview' => [
                'route' => 'property.landlord.portfolio',
                'active' => 'property.landlord.portfolio',
                'icon' => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
            ],
            'Earnings & wallet' => [
                'route' => 'property.landlord.earnings.index',
                'active' => 'property.landlord.earnings.*',
                'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-1a2 2 0 00-2-2H9a2 2 0 00-2 2v1a2 2 0 002 2z',
            ],
            'Properties' => [
                'route' => 'property.landlord.properties',
                'active' => ['property.landlord.properties*', 'property.landlord.vacancies'],
                'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            ],
            'Reports' => [
                'route' => 'property.landlord.reports.index',
                'active' => ['property.landlord.reports.*', 'property.landlord.leases.*', 'property.landlord.documents'],
                'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            ],
            'Maintenance' => [
                'route' => 'property.landlord.maintenance',
                'active' => 'property.landlord.maintenance*',
                'icon' => 'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z',
            ],
            'Notifications' => [
                'route' => 'property.landlord.notifications',
                'active' => 'property.landlord.notifications',
                'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
            ],
            'Account' => [
                'route' => 'property.landlord.settings.index',
                'active' => [
                    'property.landlord.settings.*',
                    'property.landlord.audit_trail*',
                    'property.landlord.loans*',
                    'property.landlord.earnings.settings',
                ],
                'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
            ],
        ];
    }

    /**
     * Compact header shortcuts (aligned with sidebar priorities).
     *
     * @return list<array{label: string, route: string, patterns: list<string>}>
     */
    public static function headerQuickLinks(): array
    {
        return [
            ['label' => 'Portfolio', 'route' => 'property.landlord.portfolio', 'patterns' => ['property.landlord.portfolio']],
            ['label' => 'Earnings', 'route' => 'property.landlord.earnings.index', 'patterns' => ['property.landlord.earnings.*']],
            ['label' => 'Properties', 'route' => 'property.landlord.properties', 'patterns' => ['property.landlord.properties*', 'property.landlord.vacancies']],
            ['label' => 'Reports', 'route' => 'property.landlord.reports.index', 'patterns' => ['property.landlord.reports.*', 'property.landlord.leases.*', 'property.landlord.documents']],
            ['label' => 'Maintenance', 'route' => 'property.landlord.maintenance', 'patterns' => ['property.landlord.maintenance*']],
        ];
    }
}
