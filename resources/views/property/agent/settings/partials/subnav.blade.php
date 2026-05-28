@props(['active' => null])

@php
    $user = auth()->user();
    $isSuperAdmin = (bool) ($user->is_super_admin ?? false);
    $canSystemSetup = $user->hasPmPermission('settings.manage');
    $canTeamUsers = $user->hasPmPermission('team.users.manage');
    $canViewPermissionCatalog = $user->hasPmPermission('settings.access.manage');

    $tabs = [
        ['route' => 'property.settings.commission', 'label' => 'Commission'],
        ['route' => 'property.settings.payments', 'label' => 'Payment config'],
        ['route' => 'property.settings.forwarder', 'label' => 'My SMS Forwarder'],
        ['route' => 'property.settings.branding', 'label' => 'Branding'],
        ['route' => 'property.settings.rules', 'label' => 'System rules'],
        ['route' => 'property.settings.deposits', 'label' => 'Deposit rules'],
        ['route' => 'property.settings.expenses', 'label' => 'Expense charge rules'],
    ];

    $prefixTabs = [];
    if ($isSuperAdmin || $canTeamUsers) {
        $prefixTabs[] = ['route' => 'property.settings.roles', 'label' => 'Property users'];
    }
    if ($isSuperAdmin || $canViewPermissionCatalog) {
        $prefixTabs[] = ['route' => 'property.settings.permissions', 'label' => 'Permissions'];
    }
    $tabs = array_merge($prefixTabs, $tabs);

    if ($canSystemSetup) {
        $tabs[] = ['route' => 'property.settings.system_setup', 'label' => 'System setup'];
    }

    $activeRoute = $active ?? request()->route()?->getName();
@endphp

<x-property.responsive.quick-action-grid {{ $attributes->merge(['class' => 'mb-4']) }}>
    @foreach ($tabs as $tab)
        <a
            href="{{ route($tab['route']) }}"
            data-turbo-frame="property-main"
            @class([
                'quick-action-btn border',
                $activeRoute === $tab['route']
                    ? 'bg-blue-600 border-blue-600 text-white'
                    : 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
            ])
            @if ($activeRoute === $tab['route']) aria-current="page" @endif
        >{{ $tab['label'] }}</a>
    @endforeach
</x-property.responsive.quick-action-grid>
