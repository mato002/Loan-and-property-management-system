<?php

namespace App\View\Components\Property;

use App\Support\Property\PropertyUiVersion;
use App\Support\Property\PropertyWorkspaceTabs;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class WorkspaceTabs extends Component
{
    public string $workspaceKey;

    /** @var list<array<string, mixed>> */
    public array $tabs;

    /** @var list<array<string, mixed>> */
    public array $subTabs;

    public bool $showSubTabs;

    public string $subTabGroupLabel;

    public string $workspaceLabel;

    public function __construct(?string $workspace = null)
    {
        $routeName = Route::currentRouteName();
        $this->workspaceKey = trim((string) ($workspace ?? PropertyWorkspaceTabs::resolveWorkspaceKey($routeName) ?? ''));
        $this->tabs = $this->workspaceKey !== ''
            ? PropertyWorkspaceTabs::tabsFor($this->workspaceKey)
            : [];

        $activeSubTabGroup = PropertyWorkspaceTabs::resolveActiveSubTabGroup($routeName);
        $this->subTabs = is_array($activeSubTabGroup) ? ($activeSubTabGroup['tabs'] ?? []) : [];
        $this->subTabGroupLabel = is_array($activeSubTabGroup) ? (string) ($activeSubTabGroup['label'] ?? '') : '';
        $this->showSubTabs = $this->subTabs !== [] && $this->subTabGroupLabel !== '';

        $this->workspaceLabel = $this->workspaceKey !== ''
            ? PropertyWorkspaceTabs::workspaceLabel($this->workspaceKey)
            : '';
    }

    public function tabIsActive(array $tab): bool
    {
        return PropertyWorkspaceTabs::tabIsActive($tab);
    }

    public function render(): View
    {
        return view(PropertyUiVersion::componentView('workspace-tabs'));
    }
}
